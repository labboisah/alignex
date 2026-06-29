<?php

namespace App\Services;

use App\Models\CandidateExamAttempt;
use App\Models\Exam;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RecruitmentService
{
    public function settings(Exam $exam): array
    {
        return [
            'cutoff_score' => (float) data_get($exam->settings ?? [], 'recruitment_cutoff_score', $exam->pass_mark ?? 0),
            'auto_shortlist' => (bool) data_get($exam->settings ?? [], 'recruitment_auto_shortlist', true),
            'shortlist_limit' => data_get($exam->settings ?? [], 'recruitment_shortlist_limit'),
            'negative_marking' => (bool) data_get($exam->settings ?? [], 'negative_marking', false),
            'negative_mark_value' => (float) data_get($exam->settings ?? [], 'negative_mark_value', 0),
        ];
    }

    public function updateSettings(Exam $exam, array $settings): Exam
    {
        $merged = [
            ...($exam->settings ?? []),
            'recruitment_cutoff_score' => (float) $settings['cutoff_score'],
            'recruitment_auto_shortlist' => (bool) ($settings['auto_shortlist'] ?? false),
            'recruitment_shortlist_limit' => filled($settings['shortlist_limit'] ?? null) ? (int) $settings['shortlist_limit'] : null,
            'negative_marking' => (bool) ($settings['negative_marking'] ?? false),
            'negative_mark_value' => (float) ($settings['negative_mark_value'] ?? 0),
        ];

        $exam->update([
            'pass_mark' => $settings['cutoff_score'],
            'settings' => $merged,
        ]);

        if ((bool) ($settings['auto_shortlist'] ?? false)) {
            $this->applyShortlist($exam);
        }

        return $exam->refresh();
    }

    public function generateAccessCodes(Exam $exam): int
    {
        $count = 0;

        CandidateExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->with('candidate')
            ->get()
            ->each(function (CandidateExamAttempt $attempt) use (&$count): void {
                $code = strtoupper(Str::random(8));
                $attempt->update([
                    'access_code' => $code,
                    'access_code_hash' => Hash::make($code),
                ]);
                $count++;
            });

        return $count;
    }

    public function ranking(Exam $exam): Collection
    {
        return CandidateExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->whereIn('status', [CandidateExamAttempt::STATUS_SUBMITTED, CandidateExamAttempt::STATUS_AUTO_SUBMITTED])
            ->with(['candidate', 'proctoringEvents'])
            ->get()
            ->sortByDesc(fn (CandidateExamAttempt $attempt) => (float) ($attempt->score ?? 0))
            ->values()
            ->map(function (CandidateExamAttempt $attempt, int $index) use ($exam): array {
                $candidate = $attempt->candidate;
                $score = (float) ($attempt->score ?? 0);
                $percentage = (float) ($attempt->total_marks ?? 0) > 0 ? round(($score / (float) $attempt->total_marks) * 100, 2) : 0;

                return [
                    'rank' => $index + 1,
                    'attempt_id' => $attempt->id,
                    'candidate_id' => $attempt->candidate_id,
                    'candidate_name' => trim(($candidate?->first_name ?? '').' '.($candidate?->last_name ?? '')),
                    'registration_number' => $candidate?->candidate_number,
                    'email' => $candidate?->email,
                    'phone' => $candidate?->phone,
                    'score' => $score,
                    'total_marks' => (float) ($attempt->total_marks ?? $exam->total_marks ?? 0),
                    'percentage' => $percentage,
                    'status' => $exam->candidates()->whereKey($attempt->candidate_id)->first()?->pivot?->status ?? 'assigned',
                    'submitted_at' => $attempt->submitted_at?->toISOString(),
                    'ip_address' => $attempt->ip_address,
                    'device_fingerprint' => $attempt->device_fingerprint,
                    'suspicious_events' => $attempt->proctoringEvents->count(),
                ];
            });
    }

    public function applyShortlist(Exam $exam): int
    {
        $settings = $this->settings($exam);
        $limit = $settings['shortlist_limit'] ? (int) $settings['shortlist_limit'] : null;
        $ranking = $this->ranking($exam)
            ->filter(fn (array $row) => (float) $row['score'] >= (float) $settings['cutoff_score'])
            ->when($limit, fn (Collection $rows) => $rows->take($limit));

        $shortlisted = $ranking->pluck('candidate_id')->filter()->values();

        foreach ($exam->candidates as $candidate) {
            $exam->candidates()->updateExistingPivot($candidate->id, [
                'status' => $shortlisted->contains($candidate->id) ? 'shortlisted' : 'not_shortlisted',
            ]);
        }

        return $shortlisted->count();
    }

    public function anomalies(Exam $exam): array
    {
        $attempts = CandidateExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->with(['candidate', 'auditLogs'])
            ->get();

        $duplicateLogins = $attempts
            ->filter(fn (CandidateExamAttempt $attempt) => $attempt->auditLogs->where('event_type', 'login_success')->count() > 1)
            ->map(fn (CandidateExamAttempt $attempt) => $this->anomalyRow($attempt, 'Duplicate login', $attempt->auditLogs->where('event_type', 'login_success')->count().' successful logins'))
            ->values()
            ->all();

        $sharedDevices = $attempts
            ->filter(fn (CandidateExamAttempt $attempt) => filled($attempt->device_fingerprint))
            ->groupBy('device_fingerprint')
            ->filter(fn (Collection $group) => $group->pluck('candidate_id')->unique()->count() > 1)
            ->flatMap(fn (Collection $group) => $group->map(fn (CandidateExamAttempt $attempt) => $this->anomalyRow($attempt, 'Shared device', 'Device used by '.$group->pluck('candidate_id')->unique()->count().' candidates')))
            ->values()
            ->all();

        $sharedIps = $attempts
            ->filter(fn (CandidateExamAttempt $attempt) => filled($attempt->ip_address))
            ->groupBy('ip_address')
            ->filter(fn (Collection $group) => $group->pluck('candidate_id')->unique()->count() > 1)
            ->flatMap(fn (Collection $group) => $group->map(fn (CandidateExamAttempt $attempt) => $this->anomalyRow($attempt, 'Shared IP', 'IP used by '.$group->pluck('candidate_id')->unique()->count().' candidates')))
            ->values()
            ->all();

        return [
            'duplicate_logins' => $duplicateLogins,
            'shared_devices' => $sharedDevices,
            'shared_ips' => $sharedIps,
        ];
    }

    public function shortlisted(Exam $exam): Collection
    {
        return $this->ranking($exam)->filter(fn (array $row) => $row['status'] === 'shortlisted')->values();
    }

    public function accessCodeRows(Exam $exam): Collection
    {
        return CandidateExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->with('candidate')
            ->get()
            ->map(fn (CandidateExamAttempt $attempt) => [
                'candidate_name' => trim(($attempt->candidate?->first_name ?? '').' '.($attempt->candidate?->last_name ?? '')),
                'registration_number' => $attempt->candidate?->candidate_number,
                'access_code' => $attempt->access_code,
            ]);
    }

    private function anomalyRow(CandidateExamAttempt $attempt, string $type, string $description): array
    {
        return [
            'type' => $type,
            'description' => $description,
            'candidate_name' => trim(($attempt->candidate?->first_name ?? '').' '.($attempt->candidate?->last_name ?? '')),
            'registration_number' => $attempt->candidate?->candidate_number,
            'ip_address' => $attempt->ip_address,
            'device_fingerprint' => $attempt->device_fingerprint,
        ];
    }
}
