<?php

namespace App\Services;

use App\Http\Resources\AdaptiveAttemptResource;
use App\Models\AdaptiveAreaBalance;
use App\Models\AdaptiveAttemptState;
use App\Models\AdaptiveDecision;
use App\Models\AdaptiveLevel;
use App\Models\AdaptiveLevelRun;
use App\Models\AdaptivePoolItem;
use App\Models\AdaptiveProgression;
use App\Models\AdaptiveResponse;
use App\Models\AdaptiveSnapshot;
use App\Models\CandidateExamAttempt;
use App\Models\Exam;
use App\Models\User;
use App\Support\AdaptiveSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdaptiveLifecycleService
{
    public function __construct(private readonly AdaptiveLedgerService $ledger) {}

    public function disqualify(CandidateExamAttempt $attempt, string $reason): void
    {
        $identity = AdaptiveLevel::where('attempt_id', $attempt->id)->firstOrFail();
        DB::transaction(function () use ($attempt, $reason, $identity): void {
            AdaptiveProgression::whereKey($identity->progression_id)->lockForUpdate()->firstOrFail();
            $attempt = CandidateExamAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ($attempt->status !== CandidateExamAttempt::STATUS_IN_PROGRESS) {
                return;
            }
            $attempt->update(['status' => CandidateExamAttempt::STATUS_DISQUALIFIED, 'disqualified_at' => now(), 'disqualification_reason' => $reason]);
            $this->execute($attempt, 'expire');
        }, 3);
    }

    public function endBySupervisor(CandidateExamAttempt $attempt, User $actor): void
    {
        $identity = AdaptiveLevel::where('attempt_id', $attempt->id)->firstOrFail();
        DB::transaction(function () use ($identity, $actor): void {
            $progression = AdaptiveProgression::whereKey($identity->progression_id)->lockForUpdate()->firstOrFail();
            $level = AdaptiveLevel::where('progression_id', $progression->id)->orderByDesc('number')->firstOrFail();
            $attempt = CandidateExamAttempt::whereKey($level->attempt_id)->lockForUpdate()->firstOrFail();
            $snapshot = AdaptiveSnapshot::findOrFail($progression->snapshot_id);
            $run = AdaptiveLevelRun::where('level_id', $level->id)->first();
            $this->finish($attempt, $level, $progression, $snapshot, $run, 'supervisor_end');
            $this->close($progression, $level, 'supervisor_end');
            if (! in_array($progression->fresh()->stop_reason, ['disqualified', 'supervisor_end'], true)) {
                $progression->update(['stop_reason' => 'supervisor_end', 'state_version' => $progression->state_version + 1]);
            }
            $this->ledger->reconcile($progression);
            $this->audit($attempt, 'adaptive_supervisor_end', ['actor_user_id' => $actor->id]);
        }, 3);
    }

    public function handles(CandidateExamAttempt $attempt): bool
    {
        return AdaptiveAttemptState::where('attempt_id', $attempt->id)->exists();
    }

    public function execute(CandidateExamAttempt $attempt, string $operation, array $data = []): array
    {
        // Resolve immutable identity before the transaction. Under MySQL REPEATABLE READ,
        // a plain SELECT before the lock would retain a stale snapshot after waiting.
        $identity = AdaptiveLevel::where('attempt_id', $attempt->id)->firstOrFail();

        return DB::transaction(function () use ($attempt, $operation, $data, $identity): array {
            $progression = AdaptiveProgression::whereKey($identity->progression_id)->lockForUpdate()->firstOrFail();
            $attempt = CandidateExamAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $level = AdaptiveLevel::whereKey($identity->id)->lockForUpdate()->firstOrFail();
            $state = AdaptiveAttemptState::where('attempt_id', $attempt->id)->firstOrFail();
            $snapshot = AdaptiveSnapshot::findOrFail($state->snapshot_id);
            if ($snapshot->id != $progression->snapshot_id || $snapshot->exam_id !== $attempt->exam_id
                || $progression->candidate_id !== $attempt->candidate_id || $progression->exam_id !== $attempt->exam_id
                || $state->delivery_mode !== 'adaptive') {
                throw new \LogicException('Inconsistent adaptive attempt identity.');
            }
            if ($operation !== 'expire') {
                $this->eligible($attempt, $snapshot, false);
                $this->device($attempt, $snapshot, $data['device_fingerprint'] ?? null, $operation === 'start');
            }
            $run = AdaptiveLevelRun::where('level_id', $level->id)->first();
            if ($attempt->status === CandidateExamAttempt::STATUS_DISQUALIFIED) {
                $this->finish($attempt, $level, $progression, $snapshot, $run, 'disqualified');

                return $this->payload($attempt, $level, $progression, $state, $snapshot);
            }
            if ($level->status === 'active' && $this->expired($level, $progression)) {
                $this->finish($attempt, $level, $progression, $snapshot, $run, 'timeout');
            }
            if ($operation === 'next-level') {
                return $this->nextLevel($attempt, $level, $progression, $snapshot, $data);
            }
            if ($operation === 'start' && $level->status === 'prepared') {
                app(AdaptiveRolloutService::class)->ensureDeliveryAllowed($attempt->exam);
                $this->eligible($attempt, $snapshot, true);
                $plan = $this->initialPlan($snapshot);
                $this->checkPool($snapshot, $progression, $plan);
                $run = AdaptiveLevelRun::create([
                    'progression_id' => $progression->id, 'level_id' => $level->id,
                    'start_key' => 'level-one', 'is_practice' => false, 'area_plan' => $plan,
                ]);
                $this->activate($attempt, $level, $progression, $snapshot, $run);
                $this->issue($attempt, $level, $progression, $state, $snapshot, $run, null);
            } elseif (in_array($operation, ['draft', 'commit'], true)) {
                if ($level->status !== 'active') {
                    return $this->payload($attempt, $level, $progression, $state, $snapshot);
                }
                $this->eligible($attempt, $snapshot, false);
                $this->answer($attempt, $level, $progression, $state, $snapshot, $run, $operation, $data);
            } elseif (in_array($operation, ['submit', 'auto-submit'], true) && $level->status === 'active') {
                $this->finish($attempt, $level, $progression, $snapshot, $run, $operation === 'auto-submit' ? 'auto_submit' : 'submitted');
            } elseif (! in_array($operation, ['read', 'start', 'submit', 'auto-submit', 'expire'], true)) {
                $this->reject('operation', 'Unsupported adaptive operation.');
            }
            if ($progression->closes_at && $progression->closes_at->lessThanOrEqualTo(now()) && $level->status !== 'active') {
                $this->close($progression, $level, 'deadline');
            }

            return $this->payload($attempt->fresh(), $level->fresh(), $progression->fresh(), $state->fresh(), $snapshot);
        }, 3);
    }

    private function eligible(CandidateExamAttempt $attempt, AdaptiveSnapshot $snapshot, bool $starting): void
    {
        app(AdaptivePilotService::class)->ensureCloudCandidate($attempt->exam, $attempt->candidate_id);
        Gate::forUser($attempt->candidate)->authorize('participate', $attempt);
        $exam = $attempt->exam;
        if (! $exam->candidates()->where('candidates.id', $attempt->candidate_id)->exists()
            || app(AdaptiveRolloutService::class)->ownerKey($exam) !== $snapshot->owner_key
            || ! in_array($snapshot->blueprint['category'], [Exam::CATEGORY_ASSESSMENT, Exam::CATEGORY_PRACTICE], true)
            || $snapshot->blueprint['delivery_mode'] !== 'online') {
            $this->reject('exam', 'This candidate is no longer eligible for this adaptive exam.');
        }
        if ($starting && ($exam->status !== Exam::STATUS_ACTIVE
            || ($snapshot->blueprint['starts_at'] && Carbon::parse($snapshot->blueprint['starts_at'])->isFuture()))) {
            $this->reject('exam', 'The exam is not open for starting.');
        }
        $settings = $snapshot->blueprint['exam_settings'];
        if (($settings['payment_required'] ?? false)
            && ! in_array($attempt->payment_status, ['paid', 'waived'], true)) {
            $this->reject('exam', 'Payment must be cleared before this exam.');
        }
    }

    private function device(CandidateExamAttempt $attempt, AdaptiveSnapshot $snapshot, mixed $fingerprint, bool $starting): void
    {
        if (! ($snapshot->blueprint['exam_settings']['bind_device'] ?? false)) {
            return;
        }
        if (! is_string($fingerprint) || $fingerprint === '' || strlen($fingerprint) > 255) {
            $this->reject('device_fingerprint', 'The bound device must identify itself.');
        }
        if ($attempt->device_fingerprint_hash && ! Hash::check($fingerprint, $attempt->device_fingerprint_hash)) {
            $this->reject('device_fingerprint', 'This exam is bound to another device.');
        }
        if ($starting && ! $attempt->device_fingerprint_hash) {
            $attempt->update(['device_fingerprint_hash' => Hash::make($fingerprint), 'device_fingerprint' => $fingerprint]);
        }
    }

    private function initialPlan(AdaptiveSnapshot $snapshot): array
    {
        if ($snapshot->blueprint['exam_settings']['negative_marking'] ?? false) {
            $this->reject('exam', 'The first adaptive engine supports non-negative objective scoring only.');
        }
        $plan = [];
        foreach ($snapshot->blueprint['areas'] as $area) {
            $plan[$area['area_key']] = [
                'question_count' => $area['question_count'], 'budget_units' => $area['budget_units'],
                'topic_ids' => $area['topic_ids'],
            ];
        }
        $extra = max(0, $snapshot->settings['adaptive_min_questions'] - array_sum(array_column($plan, 'question_count')));
        $keys = array_keys($plan);
        for ($i = 0; $i < $extra; $i++) {
            $plan[$keys[$i % count($keys)]]['question_count']++;
        }

        return $plan;
    }

    private function pool(AdaptiveSnapshot $snapshot, AdaptiveProgression $progression, string $area)
    {
        return AdaptivePoolItem::where('snapshot_id', $snapshot->id)->where('area_key', $area)
            ->whereNotIn('question_id', AdaptiveDecision::where('progression_id', $progression->id)->select('question_id'))
            ->orderBy('id')->get()->filter(fn ($item) => empty($item->content['image_path']))->values();
    }

    private function checkPool(AdaptiveSnapshot $snapshot, AdaptiveProgression $progression, array $plan): void
    {
        foreach ($plan as $key => $area) {
            $pool = $this->pool($snapshot, $progression, $key);
            if ($pool->count() < $area['question_count']) {
                $this->reject('exam', 'There are not enough fresh supported questions. No level or penalty was created.');
            }
            foreach ($area['topic_ids'] as $topic) {
                if (! $pool->contains(fn ($item) => ($item->content['topic_id'] ?? null) === $topic)) {
                    $this->reject('exam', 'A required topic has no fresh supported questions.');
                }
            }
        }
    }

    private function activate(CandidateExamAttempt $attempt, AdaptiveLevel $level, AdaptiveProgression $progression, AdaptiveSnapshot $snapshot, AdaptiveLevelRun $run): void
    {
        $duration = $snapshot->settings['progressive_remediation_enabled']
            ? $snapshot->settings['level_duration_minutes'] : $snapshot->blueprint['duration_minutes'];
        $due = now()->addMinutes((int) $duration);
        $limits = [$progression->closes_at];
        if ((int) $level->number === 1) {
            $limits[] = $snapshot->blueprint['ends_at'] ? Carbon::parse($snapshot->blueprint['ends_at']) : null;
        }
        foreach ($limits as $limit) {
            if ($limit && $limit->lessThan($due)) {
                $due = $limit->copy();
            }
        }
        if ($due->lessThanOrEqualTo(now())) {
            $this->reject('exam', 'The adaptive access window has closed.');
        }
        $level->update(['status' => 'active', 'started_at' => now(), 'due_at' => $due]);
        if (! $run->is_practice) {
            $progression->update(['status' => 'active', 'stop_reason' => null]);
        }
        $attempt->update([
            'status' => CandidateExamAttempt::STATUS_IN_PROGRESS, 'started_at' => now(), 'server_due_at' => $due,
            'total_questions' => array_sum(array_column($run->area_plan, 'question_count')),
            'total_marks' => $level->available_units / 100, 'certificate_eligible' => false,
        ]);
        $this->audit($attempt, 'adaptive_level_started', ['level' => $level->number, 'practice' => $run->is_practice]);
    }

    private function expired(AdaptiveLevel $level, AdaptiveProgression $progression): bool
    {
        return ($level->due_at && $level->due_at->lessThanOrEqualTo(now()))
            || ($progression->closes_at && $progression->closes_at->lessThanOrEqualTo(now()));
    }

    private function answer(CandidateExamAttempt $attempt, AdaptiveLevel $level, AdaptiveProgression $progression,
        AdaptiveAttemptState $state, AdaptiveSnapshot $snapshot, AdaptiveLevelRun $run, string $operation, array $data): void
    {
        $options = array_values(array_unique($data['selected_option_ids'] ?? []));
        sort($options);
        if ($operation === 'commit') {
            $retry = AdaptiveResponse::where('level_id', $level->id)->where('commit_key', $data['idempotency_key'])->first();
            if ($retry) {
                $decision = AdaptiveDecision::findOrFail($retry->decision_id);
                if ($decision->question_id !== $data['question_id'] || $retry->selected_options !== $options) {
                    $this->reject('idempotency_key', 'This key was already used for a different answer.');
                }

                return;
            }
        }
        if ((int) $state->state_version !== (int) $data['state_version']) {
            $this->reject('state_version', 'The attempt changed. Reload the current item before answering.');
        }
        $decision = AdaptiveDecision::where('level_id', $level->id)->where('step', $state->step)->firstOrFail();
        if ($decision->question_id !== $data['question_id'] || $decision->progression_id != $progression->id) {
            $this->reject('question_id', 'Only the current issued item may be answered.');
        }
        $response = AdaptiveResponse::where('decision_id', $decision->id)->firstOrFail();
        if ($response->committed_at) {
            $this->reject('question_id', 'This response has already been committed.');
        }
        $item = AdaptivePoolItem::findOrFail($decision->pool_item_id);
        if ($item->snapshot_id != $snapshot->id || $item->question_id !== $decision->question_id) {
            throw new \LogicException('Decision is outside the frozen pool.');
        }
        $content = $item->content;
        $allowed = array_column($content['options'], 'id');
        if (array_diff($options, $allowed) || ($content['question_type'] !== 'multiple_choice' && count($options) > 1)) {
            $this->reject('selected_option_ids', 'Choose only valid options for the current item.');
        }
        $values = ['selected_options' => $options];
        $correct = null;
        if ($operation === 'commit') {
            $key = array_column(array_filter($content['options'], fn ($option) => $option['is_correct']), 'id');
            sort($key);
            $correct = $options === $key;
            $earned = $correct && ! $run->is_practice ? (int) $decision->decision['weight_units'] : 0;
            $values += ['is_correct' => $correct, 'earned_units' => $earned, 'committed_at' => now(), 'commit_key' => $data['idempotency_key']];
            if ($earned > 0) {
                $this->ledger->post($progression, $level, $item->area_key, 'earned', $earned, 'earn:'.$decision->id);
                $level->update(['earned_units' => $level->earned_units + $earned]);
            }
        }
        $response->update($values);
        $state->update(['state_version' => $state->state_version + 1]);
        $this->audit($attempt, $operation === 'commit' ? 'adaptive_answer_committed' : 'adaptive_answer_saved', ['question_id' => $item->question_id]);
        if ($operation === 'commit') {
            $this->issue($attempt, $level, $progression, $state, $snapshot, $run, $correct);
        }
    }

    private function issue(CandidateExamAttempt $attempt, AdaptiveLevel $level, AdaptiveProgression $progression,
        AdaptiveAttemptState $state, AdaptiveSnapshot $snapshot, AdaptiveLevelRun $run, ?bool $correct): void
    {
        $decisions = AdaptiveDecision::where('level_id', $level->id)->orderBy('step')->get();
        $chosen = null;
        foreach ($run->area_plan as $areaKey => $area) {
            $used = $decisions->filter(fn ($d) => $d->decision['area_key'] === $areaKey);
            if ($used->count() >= $area['question_count']) {
                continue;
            }
            $pool = $this->pool($snapshot, $progression, $areaKey);
            $covered = $used->map(fn ($d) => $d->decision['topic_id'])->filter()->all();
            $missing = array_values(array_diff($area['topic_ids'], $covered));
            if ($missing) {
                $pool = $pool->filter(fn ($item) => ($item->content['topic_id'] ?? null) === $missing[0]);
            }
            $bands = ['easy', 'medium', 'hard'];
            $previous = $decisions->last()?->decision['difficulty'] ?? $snapshot->settings['adaptive_start_difficulty'];
            $band = array_search($previous, $bands, true);
            $target = $correct === null ? $band : max(0, min(2, $band + ($correct ? 1 : -1)));
            $chosen = $pool->sortBy(fn ($item) => sprintf('%d:%s', abs(array_search($item->difficulty, $bands, true) - $target),
                hash('sha256', $progression->id.':'.$level->number.':'.$state->step.':'.$item->id)))->first();
            if (! $chosen) {
                $this->finish($attempt, $level, $progression, $snapshot, $run, 'pool_exhausted');

                return;
            }
            $index = $used->count();
            $weight = intdiv($area['budget_units'], $area['question_count']) + ($index < $area['budget_units'] % $area['question_count'] ? 1 : 0);
            break;
        }
        if (! $chosen) {
            $this->finish($attempt, $level, $progression, $snapshot, $run, 'coverage_complete');

            return;
        }
        if ($decisions->count() >= $attempt->total_questions) {
            throw new \LogicException('Adaptive maximum length exceeded.');
        }
        $step = $state->step + 1;
        $decision = AdaptiveDecision::create([
            'progression_id' => $progression->id, 'level_id' => $level->id,
            'pool_item_id' => $chosen->id, 'question_id' => $chosen->question_id,
            'step' => $step, 'idempotency_key' => 'issue:'.$step,
            'decision' => ['area_key' => $chosen->area_key, 'topic_id' => $chosen->content['topic_id'],
                'difficulty' => $chosen->difficulty, 'weight_units' => $weight, 'policy' => 'simple-v1'],
        ]);
        AdaptiveResponse::create(['decision_id' => $decision->id, 'level_id' => $level->id, 'selected_options' => []]);
        $state->update(['step' => $step, 'state_version' => $state->state_version + 1]);
        $this->audit($attempt, 'adaptive_item_issued', ['question_id' => $chosen->question_id, 'step' => $step]);
    }

    private function finish(CandidateExamAttempt $attempt, AdaptiveLevel $level, AdaptiveProgression $progression,
        AdaptiveSnapshot $snapshot, ?AdaptiveLevelRun $run, string $reason): void
    {
        if (in_array($level->status, ['submitted', 'closed'], true)) {
            if ($reason === 'disqualified') {
                $this->close($progression, $level, $reason);
            }

            return;
        }
        $disqualified = $reason === 'disqualified';
        $level->update(['status' => $disqualified ? 'closed' : 'submitted', 'submitted_at' => now()]);
        $state = AdaptiveAttemptState::where('attempt_id', $attempt->id)->firstOrFail();
        $state->update(['stop_reason' => $reason, 'state_version' => $state->state_version + 1]);
        $auto = in_array($reason, ['timeout', 'auto_submit', 'supervisor_end'], true);
        $attempt->update([
            'status' => $disqualified ? CandidateExamAttempt::STATUS_DISQUALIFIED
                : ($auto ? CandidateExamAttempt::STATUS_AUTO_SUBMITTED : CandidateExamAttempt::STATUS_SUBMITTED),
            'submitted_at' => now(), 'auto_submitted_at' => $auto ? now() : null,
            'score' => $level->earned_units / 100, 'certificate_eligible' => false,
            'percentage' => $level->available_units ? round($level->earned_units * 100 / $level->available_units, 2) : 0,
        ]);
        if ($run && ! $run->is_practice) {
            foreach ($run->area_plan as $areaKey => $plan) {
                $ids = AdaptiveDecision::where('level_id', $level->id)->get()
                    ->filter(fn ($d) => $d->decision['area_key'] === $areaKey)->pluck('id');
                $responses = AdaptiveResponse::whereIn('decision_id', $ids)->whereNotNull('committed_at')->get();
                $correct = $responses->where('is_correct', true)->count();
                $minimum = $snapshot->settings['min_evidence_per_area'] ?? 1;
                $threshold = AdaptiveSettings::units((string) ($snapshot->settings['mastery_threshold_percent'] ?? 70));
                $mastery = $responses->count() < $minimum ? 'insufficient_evidence'
                    : ($correct * 10000 >= $threshold * $plan['question_count'] ? 'mastered' : 'weak');
                $area = AdaptiveAreaBalance::where('progression_id', $progression->id)->where('area_key', $areaKey)->firstOrFail();
                $area->update(['mastery' => $mastery, 'evidence_count' => $area->evidence_count + $responses->count()]);
                if ($mastery === 'mastered' && $area->recoverable_units > 0) {
                    $this->ledger->post($progression, $level, $areaKey, 'closed', $area->recoverable_units, 'mastered:'.$level->id.':'.$areaKey);
                }
            }
        }
        $settings = $snapshot->settings;
        if ($disqualified || in_array($reason, ['pool_exhausted', 'supervisor_end'], true)) {
            $this->close($progression, $level, $reason);
        } elseif (! $run?->is_practice) {
            $progression->refresh();
            if (! $settings['progressive_remediation_enabled']) {
                $this->close($progression, $level, 'single_level');
            } elseif ($level->number >= $settings['max_scored_levels']) {
                $this->close($progression, $level, 'level_cap');
            } elseif ($progression->recoverable_units < AdaptiveSettings::units((string) $settings['min_level_budget'])) {
                $this->close($progression, $level, 'budget_exhausted');
            } elseif ($progression->closes_at && $progression->closes_at->lessThanOrEqualTo(now())) {
                $this->close($progression, $level, 'deadline');
            } else {
                $progression->update(['status' => 'active', 'state_version' => $progression->state_version + 1]);
            }
        }
        $this->ledger->reconcile($progression);
        $this->audit($attempt, 'adaptive_level_finalized', ['level' => $level->number, 'reason' => $reason]);
    }

    private function close(AdaptiveProgression $progression, AdaptiveLevel $level, string $reason): void
    {
        $progression->refresh();
        if ($progression->status === 'closed') {
            return;
        }
        foreach (AdaptiveAreaBalance::where('progression_id', $progression->id)->where('recoverable_units', '>', 0)->get() as $area) {
            $this->ledger->post($progression, $level, $area->area_key, 'closed', $area->recoverable_units, 'close:'.$level->id.':'.$area->area_key);
        }
        $progression->update(['status' => 'closed', 'stop_reason' => $reason, 'state_version' => $progression->state_version + 1]);
    }

    private function nextLevel(CandidateExamAttempt $previous, AdaptiveLevel $previousLevel,
        AdaptiveProgression $progression, AdaptiveSnapshot $snapshot, array $data): array
    {
        $key = $data['idempotency_key'];
        $retry = AdaptiveLevelRun::where('progression_id', $progression->id)->where('start_key', $key)->first();
        if ($retry) {
            $level = AdaptiveLevel::findOrFail($retry->level_id);
            if ($level->number !== $previousLevel->number + 1 || $retry->is_practice !== (bool) ($data['practice'] ?? false)) {
                $this->reject('idempotency_key', 'This key belongs to another level operation.');
            }
            $attempt = CandidateExamAttempt::findOrFail($level->attempt_id);

            return $this->payload($attempt, $level, $progression, AdaptiveAttemptState::where('attempt_id', $attempt->id)->firstOrFail(), $snapshot)
                + ['exam_token' => app(CandidateExamSessionService::class)->makeToken($attempt)];
        }
        if ($progression->stop_reason === 'supervisor_end' || $previousLevel->status !== 'submitted' || $previous->status === CandidateExamAttempt::STATUS_DISQUALIFIED
            || AdaptiveLevel::where('progression_id', $progression->id)->where('number', '>', $previousLevel->number)->exists()
            || CandidateExamAttempt::whereIn('id', AdaptiveLevel::where('progression_id', $progression->id)->select('attempt_id'))->where('status', 'disqualified')->exists()) {
            $this->reject('exam', 'Only the latest finalized eligible level can start a recovery level.');
        }
        $settings = $snapshot->settings;
        if (! $settings['progressive_remediation_enabled']) {
            $this->reject('exam', 'Recovery levels are disabled.');
        }
        $this->eligible($previous, $snapshot, false);
        app(AdaptiveRolloutService::class)->ensureDeliveryAllowed($previous->exam);
        if ($progression->closes_at && $progression->closes_at->lessThanOrEqualTo(now())) {
            $this->close($progression, $previousLevel, 'deadline');

            return $this->payload($previous, $previousLevel, $progression, AdaptiveAttemptState::where('attempt_id', $previous->id)->firstOrFail(), $snapshot);
        }
        if ($previousLevel->submitted_at->copy()->addMinutes((int) $settings['level_cooldown_minutes'])->isFuture()) {
            $this->reject('exam', 'The recovery cooldown has not ended.');
        }
        $practice = (bool) ($data['practice'] ?? false);
        $priorRun = AdaptiveLevelRun::where('level_id', $previousLevel->id)->firstOrFail();
        if ($priorRun->is_practice || ($practice && (! $settings['allow_unscored_remediation'] || $progression->status !== 'closed'))) {
            $this->reject('exam', 'Unscored practice is not available.');
        }
        if (! $practice && ($progression->status !== 'active' || $previousLevel->number >= $settings['max_scored_levels'])) {
            $this->reject('exam', 'Scored recovery is closed.');
        }
        $recoveryPlan = app(AdaptiveRecoveryPlanService::class)->build($previousLevel, $progression, $snapshot, $practice);
        ['plan' => $plan, 'shares' => $shares, 'weakness' => $weakness,
            'incoming' => $incoming, 'available' => $available, 'penalty' => $penalty, 'rate' => $rate] = $recoveryPlan;
        if (! $practice && ($plan === [] || $available < AdaptiveSettings::units((string) $settings['min_level_budget']))) {
            $this->close($progression, $previousLevel, 'minimum_budget');

            return $this->payload($previous, $previousLevel, $progression, AdaptiveAttemptState::where('attempt_id', $previous->id)->firstOrFail(), $snapshot);
        }
        if ($plan === []) {
            $this->reject('exam', 'No unresolved questions remain for another level.');
        }
        // All readiness checks precede both attempt creation and penalty posting.
        $this->checkPool($snapshot, $progression, $plan);
        $attempt = CandidateExamAttempt::create([
            'exam_id' => $previous->exam_id, 'candidate_id' => $previous->candidate_id,
            'exam_session_id' => $previous->exam_session_id, 'center_id' => $previous->center_id,
            'exam_participant_id' => $previous->exam_participant_id, 'participant_type' => $previous->participant_type,
            'participant_id' => $previous->participant_id, 'payment_status' => $previous->payment_status,
            'attempt_number' => ((int) CandidateExamAttempt::withTrashed()->where('exam_id', $previous->exam_id)->where('candidate_id', $previous->candidate_id)->max('attempt_number')) + 1,
            'status' => 'not_started', 'access_code_hash' => Hash::make(Str::random(40)),
            'device_fingerprint_hash' => $previous->device_fingerprint_hash,
            'device_fingerprint' => $previous->device_fingerprint,
        ]);
        $level = AdaptiveLevel::create([
            'progression_id' => $progression->id, 'attempt_id' => $attempt->id, 'number' => $previousLevel->number + 1,
            'incoming_units' => $incoming, 'penalty_basis_points' => $rate, 'penalty_units' => $penalty,
            'available_units' => $available, 'weakness_snapshot' => $weakness,
        ]);
        $run = AdaptiveLevelRun::create(['progression_id' => $progression->id, 'level_id' => $level->id,
            'start_key' => $key, 'is_practice' => $practice, 'area_plan' => $plan]);
        $state = AdaptiveAttemptState::create(['attempt_id' => $attempt->id, 'snapshot_id' => $snapshot->id]);
        $this->activate($attempt, $level, $progression, $snapshot, $run);
        foreach ($shares as $areaKey => $share) {
            if ($share > 0) {
                $this->ledger->post($progression, $level, $areaKey, 'penalty', $share, 'penalty:'.$level->id.':'.$areaKey);
            }
        }
        $this->issue($attempt, $level, $progression, $state, $snapshot, $run, null);
        $this->ledger->reconcile($progression);

        return $this->payload($attempt, $level, $progression, $state, $snapshot)
            + ['exam_token' => app(CandidateExamSessionService::class)->makeToken($attempt)];
    }

    private function payload(CandidateExamAttempt $attempt, AdaptiveLevel $level, AdaptiveProgression $progression,
        AdaptiveAttemptState $state, AdaptiveSnapshot $snapshot): array
    {
        return (new AdaptiveAttemptResource($attempt, $level, $progression, $state))->resolve();
    }

    public function releasedResult(CandidateExamAttempt $attempt, ?AdaptiveProgression $progression = null): ?array
    {
        $progression ??= AdaptiveProgression::where('exam_id', $attempt->exam_id)->where('candidate_id', $attempt->candidate_id)->first();
        if (! $progression || $progression->status !== 'closed' || $progression->stop_reason === 'disqualified'
            || CandidateExamAttempt::whereIn('id', AdaptiveLevel::where('progression_id', $progression->id)->select('attempt_id'))->where('status', 'disqualified')->exists()
            || ! app(CandidateResultVisibilityService::class)->allows($attempt->exam)) {
            return null;
        }

        return ['score' => number_format($progression->earned_units / 100, 2, '.', ''),
            'total_marks' => number_format($progression->original_units / 100, 2, '.', ''),
            'result_type' => 'adaptive_recovery_aggregate'];
    }

    private function audit(CandidateExamAttempt $attempt, string $event, array $metadata): void
    {
        $id = $attempt->id;
        DB::afterCommit(function () use ($id, $event): void {
            $fresh = CandidateExamAttempt::find($id);
            if ($fresh) {
                app(ExamMonitorService::class)->broadcast($fresh->exam, $event, $fresh, ['event_type' => $event]);
            }
        });
        $attempt->exam->auditLogs()->create([
            'candidate_exam_attempt_id' => $attempt->id,
            'actor_type' => isset($metadata['actor_user_id']) ? 'supervisor' : 'candidate',
            'actor_user_id' => $metadata['actor_user_id'] ?? null,
            'event_type' => $event, 'description' => str($event)->replace('_', ' ')->toString(),
            'metadata' => $metadata, 'occurred_at' => now(),
        ]);
    }

    private function reject(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
