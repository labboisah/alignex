<?php

namespace App\Http\Controllers;

use App\Models\CandidateExamAttempt;
use App\Models\CertificateTemplate;
use App\Models\Exam;
use App\Models\User;
use App\Services\ProfessionalExamService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProfessionalExamController extends Controller
{
    public function __construct(private readonly ProfessionalExamService $professional)
    {
    }

    public function show(Request $request, Exam $exam): InertiaResponse
    {
        $this->authorizeExam($request->user(), $exam);

        return Inertia::render('Professional/Show', [
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'exam_code' => $exam->code,
                'exam_type' => $exam->examType?->code,
                'pass_mark' => $exam->pass_mark,
                'total_marks' => $exam->total_marks,
                'institution_name' => $this->professional->institutionName($exam),
                'route_base' => "/exams/{$exam->id}/certification",
            ],
            'settings' => $this->professional->settings($exam),
            'templates' => $exam->certificateTemplates()->latest()->get()->map(fn (CertificateTemplate $template) => [
                'id' => $template->id,
                'name' => $template->name,
                'title' => $template->title,
                'institution_name' => $template->institution_name,
                'logo_url' => $template->logo_url,
                'primary_color' => $template->primary_color,
                'accent_color' => $template->accent_color,
                'background_color' => $template->background_color,
                'use_logo_watermark' => $template->use_logo_watermark,
                'body' => $template->body,
                'signatory_name' => $template->signatory_name,
                'signatory_title' => $template->signatory_title,
                'is_active' => $template->is_active,
            ]),
            'attempts' => $this->professional->attemptRows($exam)->map(fn (CandidateExamAttempt $attempt) => [
                'id' => $attempt->id,
                'candidate_name' => trim(($attempt->candidate?->first_name ?? '').' '.($attempt->candidate?->last_name ?? '')),
                'registration_number' => $attempt->candidate?->candidate_number,
                'status' => $attempt->status,
                'score' => (float) ($attempt->score ?? 0),
                'total_marks' => (float) ($attempt->total_marks ?? $exam->total_marks ?? 0),
                'passed' => $this->professional->passed($attempt),
                'payment_status' => $attempt->payment_status ?? CandidateExamAttempt::PAYMENT_PENDING,
                'payment_reference' => $attempt->payment_reference,
                'attempt_number' => $attempt->attempt_number,
                'certificate_serial' => $attempt->certificate?->serial_number,
            ])->values(),
            'certificates' => $this->professional->certificateRows($exam),
        ]);
    }

    public function updateSettings(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorizeExam($request->user(), $exam);
        $data = $request->validate([
            'pass_mark' => ['required', 'numeric', 'min:0'],
            'attempt_limit' => ['required', 'integer', 'min:1', 'max:20'],
            'retake_policy' => ['required', Rule::in(['no_retake', 'failed_only', 'payment_required', 'always_allowed'])],
            'payment_required' => ['boolean'],
            'certificate_auto_generate' => ['boolean'],
            'certificate_valid_months' => ['nullable', 'integer', 'min:1', 'max:120'],
        ]);

        $this->professional->updateSettings($exam, $data);

        return back()->with('success', 'Certification settings updated.');
    }

    public function storeTemplate(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorizeExam($request->user(), $exam);
        $this->professional->saveTemplate($exam, $this->templateData($request));

        return back()->with('success', 'Certificate template saved.');
    }

    public function updateTemplate(Request $request, Exam $exam, CertificateTemplate $template): RedirectResponse
    {
        $this->authorizeExam($request->user(), $exam);
        abort_unless($template->exam_id === $exam->id, 404);
        $this->professional->saveTemplate($exam, $this->templateData($request), $template);

        return back()->with('success', 'Certificate template updated.');
    }

    public function destroyTemplate(Request $request, Exam $exam, CertificateTemplate $template): RedirectResponse
    {
        $this->authorizeExam($request->user(), $exam);
        abort_unless($template->exam_id === $exam->id, 404);
        $template->delete();

        return back()->with('success', 'Certificate template deleted.');
    }

    public function updatePayment(Request $request, Exam $exam, CandidateExamAttempt $attempt): RedirectResponse
    {
        $this->authorizeExam($request->user(), $exam);
        abort_unless($attempt->exam_id === $exam->id, 404);
        $data = $request->validate([
            'payment_status' => ['required', Rule::in([
                CandidateExamAttempt::PAYMENT_PENDING,
                CandidateExamAttempt::PAYMENT_PAID,
                CandidateExamAttempt::PAYMENT_WAIVED,
                CandidateExamAttempt::PAYMENT_FAILED,
            ])],
            'payment_reference' => ['nullable', 'string', 'max:255'],
        ]);

        $this->professional->updatePayment($attempt->load('exam.examType'), $data['payment_status'], $data['payment_reference'] ?? null);

        return back()->with('success', 'Payment status updated.');
    }

    public function generateCertificates(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorizeExam($request->user(), $exam);
        $count = $this->professional->generateForExam($exam);

        return back()->with('success', "{$count} certificates generated.");
    }

    public function generateCertificate(Request $request, Exam $exam, CandidateExamAttempt $attempt): RedirectResponse
    {
        $this->authorizeExam($request->user(), $exam);
        abort_unless($attempt->exam_id === $exam->id, 404);

        $certificate = $this->professional->generateForAttempt($attempt);

        return back()->with($certificate ? 'success' : 'error', $certificate ? 'Certificate generated.' : 'Certificate could not be generated. Confirm pass and payment status.');
    }

    public function verifyPage(): InertiaResponse
    {
        return Inertia::render('Public/VerifyCertificate');
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate(['identifier' => ['required', 'string', 'max:255']]);
        $certificate = $this->professional->verify($data['identifier']);

        return response()->json([
            'valid' => (bool) $certificate,
            'certificate' => $certificate,
        ]);
    }

    private function templateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'institution_name' => ['nullable', 'string', 'max:255'],
            'logo_url' => ['nullable', 'url', 'max:2048'],
            'primary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'background_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'use_logo_watermark' => ['boolean'],
            'body' => ['required', 'string'],
            'signatory_name' => ['nullable', 'string', 'max:255'],
            'signatory_title' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);
    }

    private function authorizeExam(User $user, Exam $exam): void
    {
        abort_unless($user->hasPermission('manageExams') || $user->hasPermission('viewReports'), 403);
        abort_unless($this->examScope($user)->whereKey($exam->id)->exists(), 403);
        abort_unless(in_array($exam->examType?->code, ['professional', 'secondary'], true), 404);
    }

    private function examScope(User $user): Builder
    {
        return Exam::query()
            ->with('examType')
            ->when(! $user->isSuperAdmin(), function (Builder $query) use ($user): void {
                $query->where(function (Builder $inner) use ($user): void {
                    $inner->whereRaw('1 = 0')
                        ->when($user->organization_id, fn ($scope) => $scope->orWhere('organization_id', $user->organization_id))
                        ->when($user->school_id, fn ($scope) => $scope->orWhere('school_id', $user->school_id))
                        ->when($user->center_id, fn ($scope) => $scope->orWhere('center_id', $user->center_id));
                });
            });
    }
}
