<?php

namespace App\Services;

use App\Models\AdaptiveAreaBalance;
use App\Models\AdaptiveAttemptState;
use App\Models\AdaptiveLevel;
use App\Models\AdaptiveMarkEntry;
use App\Models\AdaptiveProgression;
use App\Models\AdaptiveSnapshot;
use App\Models\Candidate;
use App\Models\CandidateExamAttempt;
use App\Models\Exam;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdaptiveAttemptPreparationService
{
    public function prepareAssignedCandidate(Exam $exam, Candidate $candidate): CandidateExamAttempt
    {
        return DB::transaction(function () use ($exam, $candidate): CandidateExamAttempt {
            $exam = Exam::whereKey($exam->id)->lockForUpdate()->firstOrFail();
            app(AdaptiveRolloutService::class)->ensureDeliveryAllowed($exam);
            if ($exam->effectiveMode() !== Exam::MODE_ADAPTIVE || $exam->status !== Exam::STATUS_ACTIVE
                || ($exam->ends_at && $exam->ends_at->lessThanOrEqualTo(now()))
                || ! $exam->candidates()->where('candidates.id', $candidate->id)->exists()) {
                throw ValidationException::withMessages(['exam' => 'This candidate cannot prepare an initial adaptive attempt.']);
            }
            $existing = CandidateExamAttempt::where('exam_id', $exam->id)->where('candidate_id', $candidate->id)
                ->orderByDesc('attempt_number')->first();
            if ($existing) {
                if (! AdaptiveAttemptState::where('attempt_id', $existing->id)->exists()) {
                    throw ValidationException::withMessages(['exam' => 'Existing traditional attempt history must be preserved.']);
                }

                return $existing;
            }
            $snapshot = AdaptiveSnapshot::where('exam_id', $exam->id)->latest('version')->first();
            if (! $snapshot || ! $snapshot->ready) {
                throw ValidationException::withMessages(['exam' => 'Prepare a ready adaptive snapshot before candidate access.']);
            }
            $attempt = CandidateExamAttempt::create([
                'exam_id' => $exam->id, 'candidate_id' => $candidate->id, 'attempt_number' => 1,
                'status' => CandidateExamAttempt::STATUS_NOT_STARTED, 'payment_status' => CandidateExamAttempt::PAYMENT_PENDING,
                'access_code_hash' => Hash::make(Str::random(40)),
            ]);
            $this->bind($attempt, $snapshot);

            return $attempt;
        }, 3);
    }

    // Bind a prepared attempt without starting its timer or issuing questions.
    public function bind(CandidateExamAttempt $attempt, AdaptiveSnapshot $snapshot): AdaptiveAttemptState
    {
        return DB::transaction(function () use ($attempt, $snapshot): AdaptiveAttemptState {
            $attempt = CandidateExamAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $existing = AdaptiveAttemptState::where('attempt_id', $attempt->id)->first();
            if ($existing) {
                if ((int) $existing->snapshot_id !== (int) $snapshot->id) {
                    throw ValidationException::withMessages(['exam' => 'This attempt already has a frozen adaptive snapshot.']);
                }

                return $existing;
            }
            $exam = Exam::whereKey($attempt->exam_id)->lockForUpdate()->first();
            if (! $exam || $attempt->started_at || $attempt->status !== CandidateExamAttempt::STATUS_NOT_STARTED
                || $attempt->papers()->exists() || ! $snapshot->ready || (string) $snapshot->exam_id !== (string) $exam->id
                || ! $exam->candidates()->where('candidates.id', $attempt->candidate_id)->exists()) {
                throw ValidationException::withMessages(['exam' => 'Only an assigned, unstarted, empty attempt can bind a ready snapshot from this exam.']);
            }
            $current = app(AdaptivePreparationService::class)->inspect($exam);
            if (! hash_equals($snapshot->fingerprint, $current['fingerprint'])) {
                throw ValidationException::withMessages(['exam' => 'The configuration or question pool changed. Prepare a new snapshot.']);
            }
            if (AdaptiveProgression::where('exam_id', $exam->id)->where('candidate_id', $attempt->candidate_id)->exists()) {
                throw ValidationException::withMessages(['exam' => 'Continue the existing progression; a new attempt cannot reset its budget.']);
            }
            $budget = $snapshot->blueprint['original_units'];
            $progression = AdaptiveProgression::create([
                'snapshot_id' => $snapshot->id, 'exam_id' => $exam->id, 'candidate_id' => $attempt->candidate_id,
                'owner_key' => $snapshot->owner_key, 'original_units' => $budget, 'recoverable_units' => $budget,
                'closes_at' => $snapshot->settings['progression_closes_at'] ?? $exam->ends_at,
            ]);
            $level = AdaptiveLevel::create([
                'progression_id' => $progression->id, 'attempt_id' => $attempt->id, 'number' => 1,
                'incoming_units' => $budget, 'available_units' => $budget, 'penalty_basis_points' => 0,
                'weakness_snapshot' => [],
            ]);
            foreach ($snapshot->blueprint['areas'] as $area) {
                AdaptiveAreaBalance::create([
                    'progression_id' => $progression->id, 'area_key' => $area['area_key'],
                    'original_units' => $area['budget_units'], 'recoverable_units' => $area['budget_units'],
                ]);
            }
            AdaptiveMarkEntry::create([
                'progression_id' => $progression->id, 'level_id' => $level->id, 'kind' => 'opening',
                'units' => $budget, 'idempotency_key' => 'opening', 'metadata' => ['snapshot_id' => $snapshot->id],
            ]);
            $state = AdaptiveAttemptState::create(['attempt_id' => $attempt->id, 'snapshot_id' => $snapshot->id]);
            $exam->auditLogs()->create([
                'candidate_exam_attempt_id' => $attempt->id, 'actor_type' => 'system',
                'event_type' => 'adaptive_attempt_prepared', 'description' => 'Attempt bound to an immutable adaptive snapshot; not started.',
                'metadata' => ['snapshot_id' => $snapshot->id, 'progression_id' => $progression->id], 'occurred_at' => now(),
            ]);

            return $state;
        });
    }
}
