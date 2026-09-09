<?php

namespace App\Http\Resources;

use App\Models\AdaptiveAttemptState;
use App\Models\AdaptiveDecision;
use App\Models\AdaptiveLevel;
use App\Models\AdaptiveLevelRun;
use App\Models\AdaptivePoolItem;
use App\Models\AdaptiveProgression;
use App\Models\AdaptiveResponse;
use App\Models\AdaptiveSnapshot;
use App\Models\CandidateExamAttempt;
use App\Services\AdaptiveLifecycleService;
use App\Services\AdaptivePresentationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdaptiveAttemptResource extends JsonResource
{
    public function __construct(CandidateExamAttempt $attempt, private readonly AdaptiveLevel $level,
        private readonly AdaptiveProgression $progression, private readonly AdaptiveAttemptState $state)
    {
        parent::__construct($attempt);
    }

    public function toArray(Request $request): array
    {
        $attempt = $this->resource;
        $level = $this->level;
        $progression = $this->progression;
        $state = $this->state;
        $state->refresh();
        $progression->refresh();
        $level->refresh();
        $run = AdaptiveLevelRun::where('level_id', $level->id)->first();
        $current = null;
        $selected = [];
        if ($level->status === 'active' && $level->due_at?->isFuture() && (! $progression->closes_at || $progression->closes_at->isFuture())) {
            $decision = AdaptiveDecision::where('level_id', $level->id)->where('step', $state->step)->first();
            if ($decision) {
                $item = AdaptivePoolItem::findOrFail($decision->pool_item_id);
                $current = (new AdaptiveCandidateItemResource($item))->resolve();
                $selected = AdaptiveResponse::where('decision_id', $decision->id)->firstOrFail()->selected_options;
            }
        }

        return app(AdaptivePresentationService::class)->candidate($attempt, $level, $progression, AdaptiveSnapshot::findOrFail($state->snapshot_id)) + [
            'delivery_mode' => 'adaptive', 'attempt' => ['id' => $attempt->id, 'status' => $attempt->fresh()->status,
                'started_at' => $level->started_at?->toISOString(), 'server_due_at' => $level->due_at?->toISOString()],
            'level' => (int) $level->number, 'is_practice' => $run?->is_practice ?? false,
            'state_version' => (int) $state->state_version, 'current_item' => $current, 'selected_option_ids' => $selected,
            'committed_questions' => AdaptiveResponse::where('level_id', $level->id)->whereNotNull('committed_at')->count(),
            'total_questions' => (int) $attempt->total_questions,
            'is_last_question' => $current !== null && $attempt->total_questions > 0 && $state->step >= $attempt->total_questions,
            'remaining_time' => $level->due_at ? max(0, (int) now()->diffInSeconds($level->due_at, false)) : 0,
            'server_now' => now()->toISOString(), 'stop_reason' => $state->stop_reason,
            'submitted' => in_array($level->status, ['submitted', 'closed'], true),
            'result' => app(AdaptiveLifecycleService::class)->releasedResult($attempt, $progression),
        ];
    }
}
