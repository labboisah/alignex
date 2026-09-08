<?php

namespace App\Services;

use App\Models\AdaptiveAttemptState;
use App\Models\AdaptiveDecision;
use App\Models\AdaptiveLevel;
use App\Models\AdaptiveLevelRun;
use App\Models\AdaptiveResponse;
use App\Models\CandidateExamAttempt;

class AdaptiveMonitorService
{
    public function row(CandidateExamAttempt $attempt): ?array
    {
        $level = AdaptiveLevel::where('attempt_id', $attempt->id)->first();
        if (! $level) {
            return null;
        }
        $state = AdaptiveAttemptState::where('attempt_id', $attempt->id)->firstOrFail();
        $run = AdaptiveLevelRun::where('level_id', $level->id)->first();

        return [
            'progression_id' => (int) $level->progression_id, 'level' => (int) $level->number, 'practice' => $run?->is_practice ?? false, 'stop_reason' => $state->stop_reason,
            'committed' => AdaptiveResponse::where('level_id', $level->id)->whereNotNull('committed_at')->count(),
            'history' => AdaptiveLevel::where('progression_id', $level->progression_id)->orderBy('number')->get()->map(function ($past): array {
                $run = AdaptiveLevelRun::where('level_id', $past->id)->first();

                return ['level' => (int) $past->number, 'status' => $past->status, 'practice' => $run?->is_practice ?? false,
                    'committed' => AdaptiveResponse::where('level_id', $past->id)->whereNotNull('committed_at')->count(),
                    'issued' => AdaptiveDecision::where('level_id', $past->id)->count(),
                    'started_at' => $past->started_at?->toISOString(), 'submitted_at' => $past->submitted_at?->toISOString(),
                    'stop_reason' => AdaptiveAttemptState::where('attempt_id', $past->attempt_id)->value('stop_reason')];
            })->all(),
        ];
    }
}
