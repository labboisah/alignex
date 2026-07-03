<?php

namespace App\Services;

use App\Models\CandidateExamAttempt;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Exam;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProfessionalExamService
{
    public function settings(Exam $exam): array
    {
        $prefix = $this->settingsPrefix($exam);

        return [
            'attempt_limit' => (int) data_get($exam->settings ?? [], "{$prefix}_attempt_limit", 1),
            'retake_policy' => data_get($exam->settings ?? [], "{$prefix}_retake_policy", 'no_retake'),
            'payment_required' => (bool) data_get($exam->settings ?? [], "{$prefix}_payment_required", false),
            'certificate_auto_generate' => (bool) data_get($exam->settings ?? [], "{$prefix}_certificate_auto_generate", true),
            'certificate_valid_months' => data_get($exam->settings ?? [], "{$prefix}_certificate_valid_months"),
            'pass_mark' => (float) ($exam->pass_mark ?? 0),
        ];
    }

    public function updateSettings(Exam $exam, array $settings): Exam
    {
        $prefix = $this->settingsPrefix($exam);
        $merged = [
            ...($exam->settings ?? []),
            "{$prefix}_attempt_limit" => (int) $settings['attempt_limit'],
            "{$prefix}_retake_policy" => $settings['retake_policy'],
            "{$prefix}_payment_required" => (bool) ($settings['payment_required'] ?? false),
            "{$prefix}_certificate_auto_generate" => (bool) ($settings['certificate_auto_generate'] ?? false),
            "{$prefix}_certificate_valid_months" => filled($settings['certificate_valid_months'] ?? null) ? (int) $settings['certificate_valid_months'] : null,
        ];

        $exam->update([
            'pass_mark' => $settings['pass_mark'],
            'settings' => $merged,
        ]);

        return $exam->refresh();
    }

    public function saveTemplate(Exam $exam, array $data, ?CertificateTemplate $template = null): CertificateTemplate
    {
        return DB::transaction(function () use ($exam, $data, $template): CertificateTemplate {
            if ((bool) ($data['is_active'] ?? false)) {
                $exam->certificateTemplates()->update(['is_active' => false]);
            }

            return $exam->certificateTemplates()->updateOrCreate(
                ['id' => $template?->id],
                [
                    'name' => $data['name'],
                    'title' => $data['title'],
                    'institution_name' => $data['institution_name'] ?? $this->institutionName($exam),
                    'logo_url' => $this->normalizeLogoUrl($data['logo_url'] ?? null),
                    'primary_color' => $data['primary_color'] ?? '#0F7A3A',
                    'accent_color' => $data['accent_color'] ?? '#F59E0B',
                    'background_color' => $data['background_color'] ?? '#FFFFFF',
                    'theme' => $data['theme'] ?? 'classic',
                    'paper_size' => $data['paper_size'] ?? 'a4',
                    'orientation' => $data['orientation'] ?? 'landscape',
                    'template_key' => $data['template_key'] ?? 'formal',
                    'use_logo_watermark' => (bool) ($data['use_logo_watermark'] ?? true),
                    'body' => $data['body'],
                    'signatory_name' => $data['signatory_name'] ?? null,
                    'signatory_title' => $data['signatory_title'] ?? null,
                    'is_active' => (bool) ($data['is_active'] ?? false),
                ]
            );
        });
    }

    public function generateForAttempt(CandidateExamAttempt $attempt): ?Certificate
    {
        $attempt->loadMissing(['exam.examType', 'candidate']);

        if (! $this->supports($attempt->exam)) {
            return null;
        }

        if (! $this->passed($attempt)) {
            return null;
        }

        if (! $this->paymentCleared($attempt)) {
            return null;
        }

        $template = $this->activeTemplate($attempt->exam);

        return DB::transaction(function () use ($attempt, $template): Certificate {
            $existing = Certificate::query()
                ->where('candidate_exam_attempt_id', $attempt->id)
                ->where('status', Certificate::STATUS_ISSUED)
                ->first();

            if ($existing) {
                return $existing;
            }

            $serial = $this->serial($attempt);
            $hash = $this->verificationHash($attempt, $serial);
            $prefix = $this->settingsPrefix($attempt->exam);
            $validMonths = data_get($attempt->exam?->settings ?? [], "{$prefix}_certificate_valid_months");

            return Certificate::query()->create([
                'exam_id' => $attempt->exam_id,
                'organization_id' => $attempt->exam?->organization_id,
                'secondary_school_id' => $attempt->exam?->secondary_school_id,
                'professional_school_id' => $attempt->exam?->professional_school_id,
                'cbt_center_id' => $attempt->exam?->cbt_center_id,
                'candidate_id' => $attempt->candidate_id,
                'candidate_exam_attempt_id' => $attempt->id,
                'certificate_template_id' => $template?->id,
                'serial_number' => $serial,
                'verification_hash' => $hash,
                'verification_code' => $hash,
                'status' => Certificate::STATUS_ISSUED,
                'issued_at' => now(),
                'expires_at' => $validMonths ? now()->addMonths((int) $validMonths) : null,
                'metadata' => [
                    'candidate_name' => trim(($attempt->candidate?->first_name ?? '').' '.($attempt->candidate?->last_name ?? '')),
                    'registration_number' => $attempt->candidate?->candidate_number,
                    'exam_title' => $attempt->exam?->title,
                    'exam_code' => $attempt->exam?->code,
                    'score' => (float) ($attempt->score ?? 0),
                    'total_marks' => (float) ($attempt->total_marks ?? $attempt->exam?->total_marks ?? 0),
                    'institution_name' => $template?->institution_name ?? $this->institutionName($attempt->exam),
                    'logo_url' => $this->normalizeLogoUrl($template?->logo_url) ?? '/images/logo.png',
                    'template_title' => $template?->title,
                ],
            ]);
        });
    }

    public function generateForExam(Exam $exam): int
    {
        return $this->attemptRows($exam)
            ->filter(fn (CandidateExamAttempt $attempt) => $this->passed($attempt) && $this->paymentCleared($attempt))
            ->map(fn (CandidateExamAttempt $attempt) => $this->generateForAttempt($attempt))
            ->filter()
            ->count();
    }

    public function updatePayment(CandidateExamAttempt $attempt, string $status, ?string $reference): CandidateExamAttempt
    {
        $attempt->update([
            'payment_status' => $status,
            'payment_reference' => $reference,
        ]);

        if ($this->passed($attempt->refresh()) && $this->paymentCleared($attempt) && $this->certificateAutoGenerate($attempt->exam)) {
            $this->generateForAttempt($attempt);
        }

        return $attempt->refresh();
    }

    /**
     * @return Collection<int, CandidateExamAttempt>
     */
    public function attemptRows(Exam $exam): Collection
    {
        return CandidateExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->with(['candidate', 'exam.examType', 'certificate'])
            ->orderBy('created_at')
            ->get();
    }

    public function certificateRows(Exam $exam): Collection
    {
        return Certificate::query()
            ->where('exam_id', $exam->id)
            ->with(['candidate', 'attempt', 'template'])
            ->latest('issued_at')
            ->get()
            ->map(fn (Certificate $certificate) => $this->certificateRow($certificate));
    }

    public function verify(string $identifier): ?array
    {
        $identifier = strtoupper(trim($identifier));
        $certificate = Certificate::query()
            ->where('serial_number', $identifier)
            ->orWhere('verification_hash', $identifier)
            ->orWhere('verification_code', $identifier)
            ->with(['candidate', 'exam', 'attempt', 'template'])
            ->first();

        return $certificate ? $this->certificateRow($certificate) : null;
    }

    public function activeTemplate(Exam $exam): ?CertificateTemplate
    {
        return $exam->certificateTemplates()
            ->where('is_active', true)
            ->latest()
            ->first();
    }

    public function passed(CandidateExamAttempt $attempt): bool
    {
        return in_array($attempt->status, [CandidateExamAttempt::STATUS_SUBMITTED, CandidateExamAttempt::STATUS_AUTO_SUBMITTED], true)
            && (float) ($attempt->score ?? 0) >= (float) ($attempt->exam?->pass_mark ?? 0);
    }

    public function paymentCleared(CandidateExamAttempt $attempt): bool
    {
        if (! (bool) $this->settings($attempt->exam)['payment_required']) {
            return true;
        }

        return in_array($attempt->payment_status, [CandidateExamAttempt::PAYMENT_PAID, CandidateExamAttempt::PAYMENT_WAIVED], true);
    }

    public function verificationUrl(Certificate $certificate): string
    {
        return url('/verify-certificate?serial='.$certificate->serial_number);
    }

    public function certificateAutoGenerate(?Exam $exam): bool
    {
        if (! $exam) {
            return false;
        }

        return $this->supports($exam) && (bool) $this->settings($exam)['certificate_auto_generate'];
    }

    public function supports(?Exam $exam): bool
    {
        return in_array($exam?->examType?->code, ['professional', 'secondary'], true);
    }

    private function certificateRow(Certificate $certificate): array
    {
        $score = (float) ($certificate->attempt?->score ?? 0);
        $totalMarks = (float) ($certificate->attempt?->total_marks ?? $certificate->exam?->total_marks ?? 0);

        return [
            'id' => $certificate->id,
            'serial_number' => $certificate->serial_number,
            'verification_hash' => $certificate->verification_hash,
            'verification_code' => $certificate->verification_code,
            'status' => $certificate->status,
            'candidate_name' => trim(($certificate->candidate?->first_name ?? '').' '.($certificate->candidate?->last_name ?? '')),
            'registration_number' => $certificate->candidate?->candidate_number,
            'exam_title' => $certificate->exam?->title,
            'exam_code' => $certificate->exam?->code,
            'score' => $score,
            'total_marks' => $totalMarks,
            'percentage' => $totalMarks > 0 ? round(($score / $totalMarks) * 100, 2) : 0,
            'issued_at' => $certificate->issued_at?->toISOString(),
            'expires_at' => $certificate->expires_at?->toISOString(),
            'template_title' => $certificate->template?->title,
            'institution_name' => $certificate->template?->institution_name ?? data_get($certificate->metadata ?? [], 'institution_name') ?? $this->institutionName($certificate->exam),
            'logo_url' => $this->normalizeLogoUrl($certificate->template?->logo_url ?? data_get($certificate->metadata ?? [], 'logo_url')) ?? '/images/logo.png',
            'primary_color' => $certificate->template?->primary_color ?? '#0F7A3A',
            'accent_color' => $certificate->template?->accent_color ?? '#F59E0B',
            'background_color' => $certificate->template?->background_color ?? '#FFFFFF',
            'theme' => $certificate->template?->theme ?? 'classic',
            'paper_size' => $certificate->template?->paper_size ?? 'a4',
            'orientation' => $certificate->template?->orientation ?? 'landscape',
            'template_key' => $certificate->template?->template_key ?? 'formal',
            'use_logo_watermark' => (bool) ($certificate->template?->use_logo_watermark ?? true),
            'verification_url' => $this->verificationUrl($certificate),
            'qr_payload' => $this->verificationUrl($certificate),
        ];
    }

    private function serial(CandidateExamAttempt $attempt): string
    {
        $prefix = ($attempt->exam?->examType?->code ?? null) === 'secondary' ? 'SEC' : 'PRO';

        do {
            $serial = $prefix.'-'.now()->format('Y').'-'.strtoupper(Str::random(8));
        } while (Certificate::query()->where('serial_number', $serial)->exists());

        return $serial;
    }

    private function verificationHash(CandidateExamAttempt $attempt, string $serial): string
    {
        return strtoupper(substr(hash('sha256', implode('|', [
            config('app.key'),
            $attempt->id,
            $attempt->candidate_id,
            $attempt->exam_id,
            $serial,
        ])), 0, 24));
    }

    private function settingsPrefix(?Exam $exam): string
    {
        return ($exam?->examType?->code ?? null) === 'secondary' ? 'secondary' : 'professional';
    }

    public function normalizeLogoUrl(?string $logoUrl): ?string
    {
        if (blank($logoUrl)) {
            return null;
        }

        $value = trim($logoUrl);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(https?:)?\/\//i', $value) || str_starts_with($value, 'data:')) {
            return $value;
        }

        $normalized = ltrim($value, '/');

        if (str_starts_with($normalized, 'storage/')) {
            return url('/'.$normalized);
        }

        if (str_starts_with($normalized, 'certificate-logos/')) {
            return url('/storage/'.$normalized);
        }

        if (str_starts_with($normalized, 'uploads/')) {
            return url('/'.$normalized);
        }

        if (str_starts_with($normalized, 'images/')) {
            return url('/'.$normalized);
        }

        if (str_starts_with($normalized, '/')) {
            return url($normalized);
        }

        return $value;
    }

    public function institutionName(?Exam $exam): string
    {
        if (! $exam) {
            return config('app.name', 'AlignEx');
        }

        $exam->loadMissing(['school', 'organization', 'center', 'secondarySchool', 'professionalSchool', 'cbtCenter']);

        return $exam->professionalSchool?->name
            ?? $exam->secondarySchool?->name
            ?? $exam->cbtCenter?->name
            ?? $exam->school?->name
            ?? $exam->organization?->name
            ?? $exam->center?->name
            ?? config('app.name', 'AlignEx');
    }
}
