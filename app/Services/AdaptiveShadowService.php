<?php

namespace App\Services;

use App\Models\AdaptiveAttemptState;
use App\Models\AdaptiveCalibration;
use App\Models\AdaptiveDecision;
use App\Models\AdaptiveEngineRun;
use App\Models\AdaptiveLevel;
use App\Models\AdaptiveLevelRun;
use App\Models\AdaptiveProgression;
use App\Models\AdaptiveResponse;
use App\Models\AdaptiveSnapshot;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdaptiveShadowService
{
    public function evaluate(Exam $exam, User $actor, AdaptiveCalibration $calibration, AdaptiveLevel $level, ?AdaptiveEngineRun $replay = null): AdaptiveEngineRun
    {
        $progression = AdaptiveProgression::whereKey($level->progression_id)->where('exam_id', $exam->id)->firstOrFail();
        abort_unless($progression->owner_key === app(AdaptiveRolloutService::class)->ownerKey($exam), 403);
        $request = DB::transaction(function () use ($progression, $calibration, $level, $replay): array {
            AdaptiveProgression::whereKey($progression->id)->lockForUpdate()->firstOrFail();
            $calibration = $calibration->fresh();
            if ($calibration->snapshot_id != $progression->snapshot_id || $calibration->status !== 'reviewed'
                || $calibration->owner_key !== $progression->owner_key) {
                throw ValidationException::withMessages(['calibration_id' => 'Select a reviewed, non-revoked calibration for this frozen snapshot.']);
            }
            if ($replay) {
                abort_unless($replay->calibration_id === $calibration->id && $replay->level_id === $level->id && $replay->status === 'succeeded', 404);

                return $replay->request_payload;
            }
            $run = AdaptiveLevelRun::where('level_id', $level->id)->first();
            if (! $run) {
                throw ValidationException::withMessages(['level_id' => 'Start this level before evaluating it.']);
            }
            $snapshot = AdaptiveSnapshot::findOrFail($calibration->snapshot_id);
            $pool = $snapshot->items()->get()->keyBy('id');
            $issued = AdaptiveDecision::where('progression_id', $progression->id)->pluck('pool_item_id');
            $exposures = AdaptiveDecision::whereIn('pool_item_id', $pool->keys())->selectRaw('pool_item_id, count(*) as aggregate')->groupBy('pool_item_id')->pluck('aggregate', 'pool_item_id');
            $decisions = AdaptiveDecision::where('level_id', $level->id)->orderBy('step')->get();
            $responses = AdaptiveResponse::where('level_id', $level->id)->whereNotNull('committed_at')->get()->keyBy('decision_id');
            $areas = array_keys($run->area_plan);
            $items = collect($calibration->payload['items'])->filter(fn ($item) => in_array($pool[$item['id']]->area_key, $areas, true))
                ->map(fn ($item) => [
                    'id' => (string) $item['id'], 'a' => (float) $item['a'], 'b' => (float) $item['b'],
                    'area' => $pool[$item['id']]->area_key, 'topic' => $pool[$item['id']]->content['topic_id'],
                    'eligible' => ! $issued->contains($item['id']) && ($exposures[$item['id']] ?? 0) < $item['exposure_limit'],
                ])->values()->all();
            $policy = $calibration->payload['policy'];

            return [
                'protocol' => 'alignex-shadow-v1', 'request_id' => (string) Str::uuid(),
                'state_version' => (int) AdaptiveAttemptState::where('attempt_id', $level->attempt_id)->value('state_version'),
                'calibration_fingerprint' => $calibration->fingerprint,
                'items' => $items, 'responses' => $decisions->filter(fn ($decision) => $responses->has($decision->id))
                    ->map(fn ($decision) => ['id' => (string) $decision->pool_item_id, 'correct' => $responses[$decision->id]->is_correct])->values()->all(),
                'areas' => $areas, 'required_topics' => collect($run->area_plan)->pluck('topic_ids')->flatten()->unique()->values()->all(),
                'policy' => ['min_questions' => (int) $policy['min_questions'], 'max_questions' => (int) $policy['max_questions'],
                    'min_per_area' => (int) $policy['min_per_area'], 'target_sd' => (float) $policy['target_sd'],
                    'cutpoint' => $policy['cutpoint'] === null ? null : (float) $policy['cutpoint']],
            ];
        });
        // No network request while holding candidate lifecycle locks.
        $started = hrtime(true);
        $result = null;
        $error = null;
        try {
            $result = app(AdaptiveEngineClient::class)->evaluate($request);
            if ($replay && $result != $replay->result) {
                throw new \RuntimeException('replay_mismatch');
            }
        } catch (\RuntimeException $exception) {
            $error = in_array($exception->getMessage(), ['engine_unconfigured', 'engine_unavailable', 'engine_invalid_response', 'replay_mismatch'], true)
                ? $exception->getMessage() : 'engine_unavailable';
            $result = null;
        }

        return DB::transaction(function () use ($exam, $actor, $calibration, $level, $progression, $replay, $request, $result, $error, $started) {
            AdaptiveProgression::whereKey($progression->id)->lockForUpdate()->firstOrFail();
            if ($calibration->fresh()->status !== 'reviewed') {
                $error = 'calibration_revoked';
            }
            if (! $replay && (int) AdaptiveAttemptState::where('attempt_id', $level->attempt_id)->value('state_version') !== $request['state_version']) {
                $error = 'state_changed';
            }
            $record = AdaptiveEngineRun::create([
                'calibration_id' => $calibration->id, 'progression_id' => $progression->id, 'level_id' => $level->id,
                'replay_of' => $replay?->id, 'actor_user_id' => $actor->id,
                'status' => $error ? 'failed' : 'succeeded', 'error_code' => $error,
                'request_hash' => hash('sha256', json_encode($request, JSON_THROW_ON_ERROR)),
                'request_payload' => $request, 'result' => $error ? null : $result,
                'duration_ms' => min(4294967295, (int) ((hrtime(true) - $started) / 1000000)),
            ]);
            $exam->auditLogs()->create(['actor_user_id' => $actor->id, 'actor_type' => 'user',
                'event_type' => 'adaptive_shadow_evaluated', 'description' => 'Experimental shadow evaluation recorded.',
                'metadata' => ['run_id' => $record->id, 'status' => $record->status, 'error_code' => $error], 'occurred_at' => now()]);

            return $record;
        });
    }
}
