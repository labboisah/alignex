<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CandidateAnswerRequest;
use App\Http\Resources\CandidateExamPayloadResource;
use App\Models\AdaptiveAttemptState;
use App\Models\AdaptiveSnapshot;
use App\Models\Candidate;
use App\Models\CandidateAnswer;
use App\Models\CandidateExamAttempt;
use App\Models\Exam;
use App\Models\ProctoringEvent;
use App\Services\AdaptiveAttemptPreparationService;
use App\Services\AdaptiveLifecycleService;
use App\Services\AdaptiveRolloutService;
use App\Services\CandidateExamSessionService;
use App\Services\CandidatePerformanceProfileService;
use App\Services\CandidateResultVisibilityService;
use App\Services\ExamMonitorService;
use App\Services\ExamResultService;
use App\Services\ExamStatusService;
use App\Services\ProfessionalExamService;
use App\Services\ResultManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CandidateExamController extends Controller
{
    public function __construct(private readonly CandidateExamSessionService $session) {}

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'exam_code' => ['nullable', 'string'],
            'identifier' => ['required_without_all:registration_number,phone,nin', 'nullable', 'string'],
            'registration_number' => ['required_without_all:identifier,phone,nin', 'nullable', 'string'],
            'phone' => ['required_without_all:identifier,registration_number,nin', 'nullable', 'string'],
            'nin' => ['required_without_all:identifier,registration_number,phone', 'nullable', 'string'],
            'device_fingerprint' => ['required', 'string', 'max:255'],
        ]);

        $exam = $this->examForLogin($data);

        // Frozen adaptive attempts have their own progression window and explicit start.
        // In particular, resuming Level 2 must not select the submitted Level 1 attempt.
        if ($exam && ($adaptiveCandidate = $this->candidateForLogin($exam, $data))) {
            if (app(AdaptiveRolloutService::class)->isAdaptive($exam)
                && ! CandidateExamAttempt::where('exam_id', $exam->id)->where('candidate_id', $adaptiveCandidate->id)->whereNotNull('started_at')->exists()) {
                app(AdaptiveAttemptPreparationService::class)->prepareAssignedCandidate($exam, $adaptiveCandidate);
            }
            $adaptiveAttempt = CandidateExamAttempt::where('exam_id', $exam->id)
                ->where('candidate_id', $adaptiveCandidate->id)
                ->whereIn('id', AdaptiveAttemptState::query()->select('attempt_id'))
                ->orderByDesc('attempt_number')->first();
            if ($adaptiveAttempt) {
                $this->session->ensureRolloutAccess($request, $adaptiveAttempt);
                $payload = app(AdaptiveLifecycleService::class)->execute($adaptiveAttempt, 'read', $data);
                $this->session->log($request, 'login_success', $adaptiveAttempt);

                return response()->json($payload + ['exam_token' => $this->session->makeToken($adaptiveAttempt)]);
            }
        }

        $candidate = $exam ? $this->candidateForLogin($exam, $data) : null;
        $attempt = $candidate ? CandidateExamAttempt::query()
            ->where('exam_id', $exam->id)->where('candidate_id', $candidate->id)
            ->whereNull('retake_cancelled_at')
            ->orderByDesc('attempt_number')
            ->with(['candidate', 'exam', 'papers.question.subject', 'papers.question.options'])
            ->first() : null;

        if ($exam && ! $attempt?->retake_of_attempt_id && $this->examOverdue($exam)) {
            $exam = app(ExamStatusService::class)->sync($exam);
            $this->session->log($request, 'login_failed', null, ['exam_id' => $exam->id], 'Exam time has ended.');
            throw ValidationException::withMessages(['exam_code' => 'This exam time has ended.']);
        }

        if (! $exam || ($attempt?->retake_of_attempt_id
            ? ! in_array($exam->status, [Exam::STATUS_ACTIVE, Exam::STATUS_COMPLETED], true)
            : $exam->status !== Exam::STATUS_ACTIVE)) {
            $this->session->log($request, 'login_failed', null, ['exam_code' => $data['exam_code'] ?? null], 'Exam not found or not active.');
            throw ValidationException::withMessages(['exam_code' => 'Exam not found or not active.']);
        }

        $candidate = $this->candidateForLogin($exam, $data);

        if (! $candidate) {
            $this->session->log($request, 'login_failed', null, ['exam_id' => $exam->id], 'Candidate is not assigned to this exam.');
            throw ValidationException::withMessages(['identifier' => 'Candidate is not assigned to this exam.']);
        }

        if (! $attempt || ! $attempt->papers()->exists()) {
            $this->session->log($request, 'login_failed', $attempt, ['exam_id' => $exam->id, 'candidate_id' => $candidate->id], 'Candidate paper has not been generated.');
            throw ValidationException::withMessages(['exam' => 'Candidate paper has not been generated.']);
        }

        if (in_array($attempt->status, [CandidateExamAttempt::STATUS_SUBMITTED, CandidateExamAttempt::STATUS_AUTO_SUBMITTED], true)) {
            $this->session->log($request, 'login_failed', $attempt, [], 'Candidate has already submitted.');
            throw ValidationException::withMessages(['exam' => 'This exam has already been submitted.']);
        }

        if ($attempt->status === CandidateExamAttempt::STATUS_DISQUALIFIED) {
            $this->session->log($request, 'login_failed', $attempt, [], 'Candidate is disqualified.');
            throw ValidationException::withMessages(['exam' => 'This candidate has been disqualified.']);
        }

        $this->ensureAttemptWindow($attempt);
        $this->ensureProfessionalEligibility($request, $exam, $attempt);
        $this->session->ensureRolloutAccess($request, $attempt);

        try {
            $this->bindDeviceIfRequired($exam, $attempt, $data['device_fingerprint']);
        } catch (ValidationException $exception) {
            $this->session->log($request, 'login_failed', $attempt, [], 'Device binding failed.');
            throw $exception;
        }
        if (! $this->attemptCanStart($attempt) && $attempt->status === CandidateExamAttempt::STATUS_NOT_STARTED) {
            $token = $this->session->makeToken($attempt->refresh()->load(['candidate', 'exam', 'papers.question.subject', 'papers.question.options']));
            $this->session->log($request, 'login_waiting', $attempt, [
                'starts_at' => $attempt->accessStartsAt()?->toISOString(),
                'starts_in_seconds' => $this->secondsUntilStart($attempt),
            ], 'Candidate logged in before exam start time.');

            return response()->json((new CandidateExamPayloadResource($attempt, $token))->resolve($request));
        }

        $this->startAttempt($exam, $attempt, $request, $data['device_fingerprint']);
        if ($this->session->remainingSeconds($attempt->refresh()) <= 0) {
            $attempt = $this->finalizeAttempt($request, $attempt, CandidateExamAttempt::STATUS_AUTO_SUBMITTED);
        }

        $token = $this->session->makeToken($attempt->refresh()->load(['candidate', 'exam', 'papers.question.subject', 'papers.question.options']));
        $this->session->log($request, 'login_success', $attempt, ['device_binding' => (bool) data_get($exam->settings ?? [], 'bind_device', false)]);
        app(ExamMonitorService::class)->broadcast($exam, 'login', $attempt);

        return response()->json((new CandidateExamPayloadResource($attempt, $token))->resolve($request));
    }

    public function start(Request $request): JsonResponse
    {
        $attempt = $this->session->attemptFromRequest($request);
        $data = $request->validate([
            'device_fingerprint' => ['required', 'string', 'max:255'],
        ]);
        $exam = $attempt->exam;
        if (app(AdaptiveLifecycleService::class)->handles($attempt)) {
            return response()->json(app(AdaptiveLifecycleService::class)->execute($attempt, 'start', $data));
        }

        if (! $exam || ($attempt->retake_of_attempt_id
            ? ! in_array($exam->status, [Exam::STATUS_ACTIVE, Exam::STATUS_COMPLETED], true)
            : $exam->status !== Exam::STATUS_ACTIVE)) {
            throw ValidationException::withMessages(['exam' => 'Exam not found or not active.']);
        }

        $this->ensureAttemptWindow($attempt);
        if (! $this->isOpenAttempt($attempt)) {
            throw ValidationException::withMessages(['exam' => 'This exam attempt is already closed.']);
        }

        if (! $attempt->retake_of_attempt_id && $this->examOverdue($exam)) {
            app(ExamStatusService::class)->sync($exam);
            $this->session->log($request, 'start_failed', $attempt, [], 'Exam time has ended.');
            throw ValidationException::withMessages(['exam' => 'This exam time has ended.']);
        }

        if (! $this->attemptCanStart($attempt)) {
            $this->session->log($request, 'start_waiting', $attempt, [
                'starts_at' => $attempt->accessStartsAt()?->toISOString(),
                'starts_in_seconds' => $this->secondsUntilStart($attempt),
            ], 'Candidate attempted to start before exam start time.');
            throw ValidationException::withMessages(['exam' => 'This exam has not started yet.']);
        }

        $this->bindDeviceIfRequired($exam, $attempt, $data['device_fingerprint']);

        if ($attempt->status === CandidateExamAttempt::STATUS_NOT_STARTED) {
            $this->startAttempt($exam, $attempt, $request, $data['device_fingerprint']);
            $attempt = $attempt->refresh()->load(['candidate', 'exam', 'papers.question.subject', 'papers.question.options']);
            $this->session->log($request, 'login_success', $attempt, ['device_binding' => (bool) data_get($exam->settings ?? [], 'bind_device', false)]);
            app(ExamMonitorService::class)->broadcast($exam, 'login', $attempt);
        }

        return response()->json((new CandidateExamPayloadResource($attempt))->resolve($request));
    }

    public function exam(Request $request): JsonResponse
    {
        $attempt = $this->session->attemptFromRequest($request);
        if (app(AdaptiveLifecycleService::class)->handles($attempt)) {
            return response()->json(app(AdaptiveLifecycleService::class)->execute($attempt, 'read', $request->only('device_fingerprint')));
        }

        if ($attempt->status === CandidateExamAttempt::STATUS_IN_PROGRESS && $this->session->remainingSeconds($attempt) <= 0 && $this->isOpenAttempt($attempt)) {
            $attempt = $this->finalizeAttempt($request, $attempt, CandidateExamAttempt::STATUS_AUTO_SUBMITTED);
        }

        return response()->json((new CandidateExamPayloadResource($attempt))->resolve($request));
    }

    public function answer(CandidateAnswerRequest $request): JsonResponse
    {
        $attempt = $request->attributes->get('candidate_attempt');
        if (app(AdaptiveLifecycleService::class)->handles($attempt)) {
            return response()->json(app(AdaptiveLifecycleService::class)->execute($attempt, $request->boolean('commit') ? 'commit' : 'draft', $request->validated()));
        }
        $this->session->ensureWritable($attempt);

        if ($this->session->remainingSeconds($attempt) <= 0) {
            $this->finalizeAttempt($request, $attempt, CandidateExamAttempt::STATUS_AUTO_SUBMITTED);
            throw ValidationException::withMessages(['exam' => 'Exam time has elapsed and the attempt has been submitted.']);
        }

        $data = $request->validated();

        $paper = $attempt->papers
            ->loadMissing('question.options')
            ->firstWhere('question_id', $data['question_id']);

        if (! $paper) {
            throw ValidationException::withMessages(['question_id' => 'Question is not on this candidate paper.']);
        }

        $allowedOptionIds = collect($paper->option_order ?? [])
            ->whenEmpty(fn ($options) => $paper->question?->options?->pluck('id') ?? collect())
            ->map(fn ($optionId) => (string) $optionId)
            ->all();

        $selectedOptionIds = collect($data['selected_option_ids'] ?? [])
            ->map(fn ($optionId) => (string) $optionId)
            ->filter(fn ($optionId) => in_array($optionId, $allowedOptionIds, true))
            ->values()
            ->all();

        [$answer, $attempt] = DB::transaction(function () use ($request, $attempt, $paper, $selectedOptionIds, $data): array {
            $attempt = CandidateExamAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $this->session->ensureWritable($attempt);
            if ($this->session->remainingSeconds($attempt) <= 0) {
                return [null, $this->finalizeAttempt($request, $attempt, CandidateExamAttempt::STATUS_AUTO_SUBMITTED)];
            }
            $answer = CandidateAnswer::query()->updateOrCreate(
                [
                    'candidate_exam_attempt_id' => $attempt->id,
                    'question_id' => $paper->question_id,
                ],
                [
                    'subject_id' => $paper->question?->subject_id,
                    'answer_payload' => ['selected_option_ids' => $selectedOptionIds],
                    'selected_option_ids' => $selectedOptionIds,
                    'is_flagged' => (bool) ($data['is_flagged'] ?? false),
                    'time_spent_seconds' => (int) ($data['time_spent_seconds'] ?? 0),
                    'ip_address' => $request->ip(),
                    'device_fingerprint' => $data['device_fingerprint'] ?? $attempt->device_fingerprint,
                    'saved_at' => now(),
                ]
            );

            $attempt = $this->refreshAttemptProgress($attempt);
            $this->session->log($request, 'answer_saved', $attempt, ['question_id' => $paper->question_id]);

            return [$answer, $attempt];
        });

        if (! $answer) {
            throw ValidationException::withMessages(['exam' => 'Exam time has elapsed and the attempt has been submitted.']);
        }
        $answeredQuestions = $this->answeredQuestions($attempt);

        try {
            app(ExamMonitorService::class)->broadcast($attempt->exam, 'answer_saved', $attempt, [
                'question_id' => $paper->question_id,
                'answered_questions' => $answeredQuestions,
                'percentage' => $attempt->percentage,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Candidate answer saved but monitor broadcast failed.', [
                'attempt_id' => $attempt->id,
                'exam_id' => $attempt->exam_id,
                'question_id' => $paper->question_id,
                'error' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'saved' => true,
            'question_id' => $answer->question_id,
            'saved_at' => $answer->saved_at?->toISOString(),
            'answered_questions' => $answeredQuestions,
            'percentage' => $attempt->percentage,
            'remaining_time' => $this->session->remainingSeconds($attempt),
        ]);
    }

    private function refreshAttemptProgress(CandidateExamAttempt $attempt): CandidateExamAttempt
    {
        $answered = $this->answeredQuestions($attempt);
        $totalQuestions = max(0, (int) $attempt->total_questions);

        $attempt->forceFill([
            'percentage' => $totalQuestions > 0 ? round(($answered / $totalQuestions) * 100, 2) : 0,
        ])->save();

        return $attempt->refresh()->load(['candidate', 'exam', 'answers', 'auditLogs', 'proctoringEvents']);
    }

    private function answeredQuestions(CandidateExamAttempt $attempt): int
    {
        return $attempt->answers()
            ->whereNotNull('saved_at')
            ->get(['selected_option_ids'])
            ->filter(fn (CandidateAnswer $answer) => ! empty($answer->selected_option_ids))
            ->count();
    }

    public function submit(Request $request): JsonResponse
    {
        $attempt = $this->session->attemptFromRequest($request);
        if (app(AdaptiveLifecycleService::class)->handles($attempt)) {
            return response()->json(app(AdaptiveLifecycleService::class)->execute($attempt, 'submit', $request->only('device_fingerprint')));
        }
        $this->session->ensureWritable($attempt);

        $status = $this->session->remainingSeconds($attempt) <= 0
            ? CandidateExamAttempt::STATUS_AUTO_SUBMITTED
            : CandidateExamAttempt::STATUS_SUBMITTED;

        $attempt = $this->finalizeAttempt($request, $attempt, $status);

        return response()->json([
            'submitted' => true,
            'status' => $attempt->status,
            ...(app(CandidateResultVisibilityService::class)->allows($attempt->exam) ? ['score' => $attempt->score, 'total_marks' => $attempt->total_marks] : []),
        ]);
    }

    public function autoSubmit(Request $request): JsonResponse
    {
        $attempt = $this->session->attemptFromRequest($request);
        if (app(AdaptiveLifecycleService::class)->handles($attempt)) {
            return response()->json(app(AdaptiveLifecycleService::class)->execute($attempt, 'auto-submit', $request->only('device_fingerprint')));
        }
        $this->session->ensureWritable($attempt);

        $attempt = $this->finalizeAttempt($request, $attempt, CandidateExamAttempt::STATUS_AUTO_SUBMITTED);

        return response()->json([
            'submitted' => true,
            'status' => $attempt->status,
            ...(app(CandidateResultVisibilityService::class)->allows($attempt->exam) ? ['score' => $attempt->score, 'total_marks' => $attempt->total_marks] : []),
        ]);
    }

    public function event(Request $request): JsonResponse
    {
        $attempt = $this->session->attemptFromRequest($request);
        $data = $request->validate([
            'event_type' => ['required', 'string', 'max:100'],
            'metadata' => ['array'],
        ]);

        $metadata = $this->prepareEventMetadata($attempt, $data['metadata'] ?? []);
        $this->session->log($request, $data['event_type'], $attempt, $metadata);
        $type = $this->monitorEventType($data['event_type']);
        $disqualified = false;
        $tabSwitchCount = null;

        if (in_array($type, ['suspicious', 'webcam'], true)) {
            ProctoringEvent::query()->create([
                'exam_id' => $attempt->exam_id,
                'exam_session_id' => $attempt->exam_session_id,
                'candidate_exam_attempt_id' => $attempt->id,
                'candidate_id' => $attempt->candidate_id,
                'center_id' => $attempt->center_id,
                'event_type' => $data['event_type'],
                'severity' => data_get($metadata, 'severity', 'warning'),
                'source' => 'candidate_app',
                'payload' => $metadata,
                'occurred_at' => now(),
            ]);

            if (in_array($data['event_type'], ['tab_switch', 'window_blur'], true)) {
                $tabSwitchCount = $attempt->proctoringEvents()
                    ->whereIn('event_type', ['tab_switch', 'window_blur'])
                    ->count();
                $maxTabSwitches = (int) data_get($this->proctorSettings($attempt), 'max_tab_switches', 0);

                if ($maxTabSwitches > 0 && $tabSwitchCount > $maxTabSwitches && $this->isOpenAttempt($attempt)) {
                    if (app(AdaptiveLifecycleService::class)->handles($attempt)) {
                        app(AdaptiveLifecycleService::class)->disqualify($attempt, 'Maximum tab switches exceeded.');
                    } else {
                        $attempt->update([
                            'status' => CandidateExamAttempt::STATUS_DISQUALIFIED,
                            'disqualified_at' => now(),
                            'disqualification_reason' => 'Maximum tab switches exceeded.',
                        ]);
                    }
                    $disqualified = true;
                    $this->session->log($request, 'disqualified', $attempt, [
                        'reason' => 'Maximum tab switches exceeded.',
                        'tab_switch_count' => $tabSwitchCount,
                        'max_tab_switches' => $maxTabSwitches,
                    ]);
                }
            }
        }

        app(ExamMonitorService::class)->broadcast($attempt->exam, $disqualified ? 'disqualified' : $type, $attempt->refresh(), [
            'event_type' => $data['event_type'],
            'metadata' => $metadata,
            'tab_switch_count' => $tabSwitchCount,
            'disqualified' => $disqualified,
        ]);

        return response()->json([
            'logged' => true,
            'disqualified' => $disqualified,
            'tab_switch_count' => $tabSwitchCount,
        ]);
    }

    private function candidateForLogin(Exam $exam, array $data): ?Candidate
    {
        $identifiers = collect([
            $data['identifier'] ?? null,
            $data['registration_number'] ?? null,
            $data['phone'] ?? null,
            $data['nin'] ?? null,
        ])
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => trim((string) $value))
            ->values();

        return $exam->candidates()
            ->where(function ($query) use ($identifiers): void {
                foreach ($identifiers as $identifier) {
                    $query
                        ->orWhere('candidate_number', $identifier)
                        ->orWhere('phone', $identifier)
                        ->orWhere('nin', $identifier)
                        ->orWhere('metadata->nin', $identifier);
                }
            })
            ->first();
    }

    private function examForLogin(array $data): ?Exam
    {
        if (filled($data['exam_code'] ?? null)) {
            return Exam::query()
                ->where('code', strtoupper(trim($data['exam_code'])))
                ->first();
        }

        $identifiers = collect([
            $data['identifier'] ?? null,
            $data['registration_number'] ?? null,
            $data['phone'] ?? null,
            $data['nin'] ?? null,
        ])
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => trim((string) $value))
            ->values();

        if ($identifiers->isEmpty()) {
            return null;
        }

        return Exam::query()
            ->where(function ($available) use ($identifiers): void {
                $available->where('status', Exam::STATUS_ACTIVE)
                    ->orWhere(function ($completed) use ($identifiers): void {
                        $completed->where('status', Exam::STATUS_COMPLETED)
                            ->whereHas('attempts', function ($retake) use ($identifiers): void {
                                $retake->whereNotNull('retake_of_attempt_id')->whereNull('retake_cancelled_at')
                                    ->whereIn('status', [CandidateExamAttempt::STATUS_NOT_STARTED, CandidateExamAttempt::STATUS_IN_PROGRESS])
                                    ->where(function ($open): void {
                                        $open->where('retake_ends_at', '>', now())->orWhere('status', CandidateExamAttempt::STATUS_IN_PROGRESS);
                                    })
                                    ->whereHas('candidate', function ($candidate) use ($identifiers): void {
                                        $candidate->where(function ($identity) use ($identifiers): void {
                                            foreach ($identifiers as $identifier) {
                                                $identity->orWhere('candidate_number', $identifier)->orWhere('phone', $identifier)
                                                    ->orWhere('nin', $identifier)->orWhere('metadata->nin', $identifier);
                                            }
                                        });
                                    });
                            });
                    });
            })
            ->whereHas('candidates', function ($query) use ($identifiers): void {
                $query->where(function ($candidateQuery) use ($identifiers): void {
                    foreach ($identifiers as $identifier) {
                        $candidateQuery
                            ->orWhere('candidate_number', $identifier)
                            ->orWhere('phone', $identifier)
                            ->orWhere('nin', $identifier)
                            ->orWhere('metadata->nin', $identifier);
                    }
                });
            })
            ->latest('starts_at')
            ->first();
    }

    private function bindDeviceIfRequired(Exam $exam, CandidateExamAttempt $attempt, string $fingerprint): void
    {
        if (! (bool) data_get($exam->settings ?? [], 'bind_device', false)) {
            return;
        }

        if (! $attempt->device_fingerprint_hash) {
            $attempt->update([
                'device_fingerprint_hash' => Hash::make($fingerprint),
                'device_fingerprint' => $fingerprint,
            ]);

            return;
        }

        if (! Hash::check($fingerprint, $attempt->device_fingerprint_hash)) {
            throw ValidationException::withMessages(['device_fingerprint' => 'This exam is bound to another device.']);
        }
    }

    private function startAttempt(Exam $exam, CandidateExamAttempt $attempt, Request $request, string $fingerprint): void
    {
        DB::transaction(function () use ($exam, $attempt, $request, $fingerprint): void {
            $exam = Exam::whereKey($exam->id)->lockForUpdate()->firstOrFail();
            $locked = CandidateExamAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $locked->setRelation('exam', $exam);
            $this->ensureAttemptWindow($locked);
            if (! $this->isOpenAttempt($locked) || ! $this->attemptCanStart($locked)) {
                throw ValidationException::withMessages(['exam' => 'This attempt cannot be started.']);
            }
            if ($locked->status === CandidateExamAttempt::STATUS_IN_PROGRESS) {
                return;
            }
            $startedAt = now();
            $dueAt = $startedAt->copy()->addMinutes($locked->durationMinutes());
            $endsAt = $locked->accessEndsAt();
            if ($endsAt && $endsAt->lessThan($dueAt)) {
                $dueAt = $endsAt;
            }
            $locked->update([
                'status' => CandidateExamAttempt::STATUS_IN_PROGRESS,
                'started_at' => $startedAt,
                'server_due_at' => $dueAt,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'device_fingerprint' => $locked->device_fingerprint ?: $fingerprint,
            ]);
        });
    }

    private function ensureAttemptWindow(CandidateExamAttempt $attempt): void
    {
        if ($attempt->retake_cancelled_at) {
            throw ValidationException::withMessages(['exam' => 'This retake has been cancelled.']);
        }
        if ($attempt->retake_of_attempt_id && (! $attempt->retake_starts_at || ! $attempt->retake_ends_at
            || ! in_array($attempt->exam->status, [Exam::STATUS_ACTIVE, Exam::STATUS_COMPLETED], true))) {
            throw ValidationException::withMessages(['exam' => 'This retake is not available.']);
        }
        if ($attempt->status === CandidateExamAttempt::STATUS_NOT_STARTED
            && $attempt->accessEndsAt()?->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages(['exam' => 'This exam attempt window has closed.']);
        }
    }

    private function isOpenAttempt(CandidateExamAttempt $attempt): bool
    {
        return ! in_array($attempt->status, [
            CandidateExamAttempt::STATUS_SUBMITTED,
            CandidateExamAttempt::STATUS_AUTO_SUBMITTED,
            CandidateExamAttempt::STATUS_DISQUALIFIED,
        ], true);
    }

    private function examOverdue(Exam $exam): bool
    {
        return $exam->ends_at !== null && $exam->ends_at->isPast();
    }

    private function attemptCanStart(CandidateExamAttempt $attempt): bool
    {
        return ! $attempt->accessStartsAt() || $attempt->accessStartsAt()->lessThanOrEqualTo(now());
    }

    private function secondsUntilStart(CandidateExamAttempt $attempt): int
    {
        return $attempt->accessStartsAt()
            ? max(0, (int) ceil(now()->diffInSeconds($attempt->accessStartsAt(), false))) : 0;
    }

    private function finalizeAttempt(Request $request, CandidateExamAttempt $attempt, string $status): CandidateExamAttempt
    {
        return DB::transaction(function () use ($request, $attempt, $status): CandidateExamAttempt {
            $attempt = CandidateExamAttempt::query()
                ->whereKey($attempt->id)
                ->lockForUpdate()
                ->with(['exam', 'papers.question.subject', 'papers.question.options', 'answers.question.options'])
                ->firstOrFail();

            if (! $this->isOpenAttempt($attempt)) {
                return $attempt;
            }

            $submittedAt = now();

            $attempt->update([
                'status' => $status,
                'submitted_at' => $submittedAt,
                'auto_submitted_at' => $status === CandidateExamAttempt::STATUS_AUTO_SUBMITTED ? $submittedAt : $attempt->auto_submitted_at,
            ]);
            $attempt = app(ExamResultService::class)->calculate($attempt, true);
            app(ResultManagementService::class)->ensureHash($attempt);
            app(CandidatePerformanceProfileService::class)->generate($attempt);

            if ($attempt->certificate_eligible && app(ProfessionalExamService::class)->certificateAutoGenerate($attempt->exam)) {
                app(ProfessionalExamService::class)->generateForAttempt($attempt);
            }

            $this->session->log(
                $request,
                $status === CandidateExamAttempt::STATUS_AUTO_SUBMITTED ? 'auto_submit' : 'submission',
                $attempt,
                ['score' => $attempt->score, 'total_marks' => $attempt->total_marks]
            );
            app(ExamMonitorService::class)->broadcast(
                $attempt->exam,
                $status === CandidateExamAttempt::STATUS_AUTO_SUBMITTED ? 'auto_submit' : 'submitted',
                $attempt,
                ['score' => $attempt->score, 'total_marks' => $attempt->total_marks]
            );

            return $attempt->refresh()->load(['candidate', 'exam', 'papers.question.subject', 'papers.question.options']);
        });
    }

    private function ensureProfessionalEligibility(Request $request, Exam $exam, CandidateExamAttempt $attempt): void
    {
        if (($exam->examType?->code ?? null) !== 'professional') {
            return;
        }

        $settings = app(ProfessionalExamService::class)->settings($exam);

        // A manually scheduled retake is an audited exception to the normal attempt limit.
        if (! $attempt->retake_of_attempt_id && $attempt->attempt_number > (int) $settings['attempt_limit']) {
            $this->session->log($request, 'login_failed', $attempt, ['attempt_limit' => $settings['attempt_limit']], 'Professional attempt limit exceeded.');
            throw ValidationException::withMessages(['exam' => 'Attempt limit has been reached for this professional exam.']);
        }

        if ((bool) $settings['payment_required'] && ! in_array($attempt->payment_status, [CandidateExamAttempt::PAYMENT_PAID, CandidateExamAttempt::PAYMENT_WAIVED], true)) {
            $this->session->log($request, 'login_failed', $attempt, ['payment_status' => $attempt->payment_status], 'Professional exam payment is not cleared.');
            throw ValidationException::withMessages(['exam' => 'Payment is required before this professional exam can be started.']);
        }
    }

    private function monitorEventType(string $eventType): string
    {
        return match (true) {
            $eventType === 'webcam_heartbeat' => 'webcam',
            str_contains($eventType, 'disconnect') => 'disconnected',
            str_contains($eventType, 'focus'),
            str_contains($eventType, 'blur'),
            str_contains($eventType, 'tab'),
            str_contains($eventType, 'fullscreen'),
            str_contains($eventType, 'copy'),
            str_contains($eventType, 'paste'),
            str_contains($eventType, 'right_click'),
            str_contains($eventType, 'context_menu'),
            str_contains($eventType, 'print_screen'),
            str_contains($eventType, 'webcam') => 'suspicious',
            default => $eventType,
        };
    }

    /** @return array<string, mixed> */
    private function proctorSettings(CandidateExamAttempt $attempt): array
    {
        $state = AdaptiveAttemptState::where('attempt_id', $attempt->id)->first();

        return $state
            ? AdaptiveSnapshot::findOrFail($state->snapshot_id)->blueprint['exam_settings']
            : ($attempt->exam?->settings ?? []);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function prepareEventMetadata(CandidateExamAttempt $attempt, array $metadata): array
    {
        unset($metadata['snapshot_path'], $metadata['snapshot_url']);
        $snapshot = data_get($metadata, 'webcam_snapshot');

        if (! is_string($snapshot) || ! (bool) data_get($this->proctorSettings($attempt), 'require_webcam', false)) {
            unset($metadata['webcam_snapshot']);

            return $metadata;
        }

        if (! preg_match('/^data:image\/(jpeg|jpg|png);base64,/', $snapshot, $matches)) {
            unset($metadata['webcam_snapshot']);

            return $metadata;
        }

        $binary = base64_decode(substr($snapshot, strpos($snapshot, ',') + 1), true);

        if ($binary === false) {
            unset($metadata['webcam_snapshot']);

            return $metadata;
        }

        $extension = $matches[1] === 'png' ? 'png' : 'jpg';
        $path = 'proctoring-snapshots/'.$attempt->id.'-'.Str::ulid().'.'.$extension;
        Storage::disk('public')->put($path, $binary);

        unset($metadata['webcam_snapshot']);
        $metadata['snapshot_path'] = $path;
        $metadata['snapshot_url'] = Storage::disk('public')->url($path);

        return $metadata;
    }
}
