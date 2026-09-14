<?php

namespace App\Services;

use App\Models\AdaptiveAttemptState;
use App\Models\CandidateExamAttempt;
use App\Models\Exam;
use App\Models\ExamAuditLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CandidateRetakeService
{
    public function schedule(CandidateExamAttempt $previous, array $data, User $user): CandidateExamAttempt
    {
        Gate::forUser($user)->authorize('scheduleRetake', $previous);

        return DB::transaction(function () use ($previous, $data, $user): CandidateExamAttempt {
            // Serialize scheduling, including simultaneous requests for the same candidate.
            $exam = Exam::whereKey($previous->exam_id)->lockForUpdate()->firstOrFail();
            $previous = CandidateExamAttempt::whereKey($previous->id)->lockForUpdate()->firstOrFail();
            if (! in_array($exam->status, [Exam::STATUS_ACTIVE, Exam::STATUS_COMPLETED], true)
                || app(AdaptiveRolloutService::class)->isAdaptive($exam)
                || AdaptiveAttemptState::whereIn('attempt_id', $exam->attempts()->select('id'))->exists()) {
                $this->fail('Retakes require an active or completed traditional exam. Adaptive progressions cannot be reset with a retake.');
            }
            if (! $previous->candidate_id || ! $exam->candidates()->where('candidates.id', $previous->candidate_id)->exists()) {
                $this->fail('The candidate must still be assigned to this exam.');
            }
            if (! in_array($previous->status, [CandidateExamAttempt::STATUS_SUBMITTED, CandidateExamAttempt::STATUS_AUTO_SUBMITTED, CandidateExamAttempt::STATUS_DISQUALIFIED], true)) {
                $this->fail('Only a closed attempt can be retaken.');
            }
            $attempts = CandidateExamAttempt::where('exam_id', $exam->id)->where('candidate_id', $previous->candidate_id);
            if ((clone $attempts)->whereIn('status', [CandidateExamAttempt::STATUS_NOT_STARTED, CandidateExamAttempt::STATUS_IN_PROGRESS])
                ->whereNull('retake_cancelled_at')->where(function ($query): void {
                    $query->where('status', CandidateExamAttempt::STATUS_IN_PROGRESS)
                        ->orWhereNull('retake_ends_at')->orWhere('retake_ends_at', '>', now());
                })->exists()) {
                $this->fail('This candidate already has a pending or running attempt.');
            }
            if ((clone $attempts)->where('attempt_number', '>', $previous->attempt_number)
                ->whereIn('status', [CandidateExamAttempt::STATUS_SUBMITTED, CandidateExamAttempt::STATUS_AUTO_SUBMITTED, CandidateExamAttempt::STATUS_DISQUALIFIED])->exists()) {
                $this->fail('Schedule the retake from the latest closed attempt.');
            }
            $startsAt = Carbon::parse($data['starts_at'])->setTimezone(config('app.timezone'));
            $endsAt = Carbon::parse($data['ends_at'])->setTimezone(config('app.timezone'));
            if ($startsAt->isPast() || $endsAt->lessThanOrEqualTo($startsAt)
                || (int) $data['duration_minutes'] < 1 || (int) $data['duration_minutes'] > 1440
                || $startsAt->diffInMinutes($endsAt) < (int) $data['duration_minutes']) {
                $this->fail('The retake window must be in the future and allow the full exam duration.');
            }
            $papers = $previous->papers()->with('question')->orderBy('question_order')->get();
            if ($papers->isEmpty() || $papers->contains(fn ($paper) => ! $paper->question)) {
                $this->fail('The previous paper is unavailable. Restore its questions before scheduling a retake.');
            }
            $attempt = CandidateExamAttempt::create([
                'candidate_id' => $previous->candidate_id,
                'exam_id' => $exam->id,
                'exam_participant_id' => $previous->exam_participant_id,
                'participant_type' => $previous->participant_type,
                'participant_id' => $previous->participant_id,
                'center_id' => $previous->center_id,
                'access_code_hash' => Hash::make(Str::random(32)),
                'payment_status' => $previous->payment_status,
                'payment_reference' => $previous->payment_reference,
                'attempt_number' => (int) (clone $attempts)->withTrashed()->max('attempt_number') + 1,
                'status' => CandidateExamAttempt::STATUS_NOT_STARTED,
                'retake_of_attempt_id' => $previous->id,
                'retake_starts_at' => $startsAt,
                'retake_ends_at' => $endsAt,
                'retake_duration_minutes' => $data['duration_minutes'],
                'retake_reason' => $data['reason'],
                'retake_scheduled_by' => $user->id,
                'total_questions' => $papers->count(),
                'total_marks' => $papers->sum(fn ($paper) => $paper->scoringMarks()),
            ]);
            foreach ($papers as $paper) {
                $attempt->papers()->create([
                    'exam_participant_id' => $attempt->exam_participant_id,
                    'question_id' => $paper->question_id,
                    'question_order' => $paper->question_order,
                    'option_order' => $paper->option_order,
                    'marks' => $paper->scoringMarks(),
                ]);
            }
            $this->audit($attempt, $user, 'retake_scheduled', $data);

            return $attempt;
        }, 3);
    }

    public function candidates(Exam $exam): array
    {
        return $exam->attempts()->whereNotNull('candidate_id')->with('candidate')
            ->orderByDesc('attempt_number')->get()->groupBy('candidate_id')
            ->map(function ($attempts): ?array {
                $latest = $attempts->first();
                $closed = $attempts->first(fn ($item) => in_array($item->status, [
                    CandidateExamAttempt::STATUS_SUBMITTED, CandidateExamAttempt::STATUS_AUTO_SUBMITTED, CandidateExamAttempt::STATUS_DISQUALIFIED,
                ], true));
                if (! $closed || ! $latest->candidate) {
                    return null;
                }
                $pending = $attempts->first(fn ($item) => ! $item->retake_cancelled_at
                    && ($item->status === CandidateExamAttempt::STATUS_IN_PROGRESS
                        || ($item->status === CandidateExamAttempt::STATUS_NOT_STARTED
                            && (! $item->retake_ends_at || $item->retake_ends_at->isFuture()))));

                return [
                    'attempt_id' => $closed->id,
                    'candidate_name' => trim($latest->candidate->first_name.' '.$latest->candidate->last_name),
                    'registration_number' => $latest->candidate->candidate_number,
                    'attempt_number' => $latest->attempt_number,
                    'pending_id' => $pending?->id,
                    'pending_status' => $pending?->status,
                    'starts_at' => $pending?->accessStartsAt()?->toISOString(),
                    'ends_at' => $pending?->accessEndsAt()?->toISOString(),
                    'can_cancel' => $pending?->retake_of_attempt_id && $pending->status === CandidateExamAttempt::STATUS_NOT_STARTED,
                ];
            })->filter()->values()->all();
    }

    public function cancel(CandidateExamAttempt $attempt, User $user): void
    {
        Gate::forUser($user)->authorize('scheduleRetake', $attempt);
        DB::transaction(function () use ($attempt, $user): void {
            Exam::whereKey($attempt->exam_id)->lockForUpdate()->firstOrFail();
            $attempt = CandidateExamAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if (! $attempt->retake_of_attempt_id || $attempt->status !== CandidateExamAttempt::STATUS_NOT_STARTED || $attempt->started_at || $attempt->retake_cancelled_at) {
                $this->fail('Only a retake that has not started can be cancelled.');
            }
            $attempt->update(['retake_cancelled_at' => now()]);
            $this->audit($attempt, $user, 'retake_cancelled');
        }, 3);
    }

    private function audit(CandidateExamAttempt $attempt, User $user, string $event, array $metadata = []): void
    {
        ExamAuditLog::create([
            'exam_id' => $attempt->exam_id, 'candidate_exam_attempt_id' => $attempt->id,
            'actor_user_id' => $user->id, 'actor_type' => 'user', 'event_type' => $event,
            'description' => str($event)->replace('_', ' ')->headline()->toString(),
            'metadata' => $metadata + ['previous_attempt_id' => $attempt->retake_of_attempt_id, 'attempt_number' => $attempt->attempt_number],
            'occurred_at' => now(),
        ]);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['retake' => $message]);
    }
}
