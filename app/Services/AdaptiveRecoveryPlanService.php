<?php

namespace App\Services;

use App\Models\AdaptiveAreaBalance;
use App\Models\AdaptiveDecision;
use App\Models\AdaptiveLevel;
use App\Models\AdaptiveLevelRun;
use App\Models\AdaptiveProgression;
use App\Models\AdaptiveResponse;
use App\Models\AdaptiveSnapshot;
use App\Support\AdaptiveSettings;

class AdaptiveRecoveryPlanService
{
    public function build(AdaptiveLevel $previous, AdaptiveProgression $progression, AdaptiveSnapshot $snapshot, bool $practice = false): array
    {
        $prior = AdaptiveLevelRun::where('level_id', $previous->id)->firstOrFail();
        $rate = $practice ? 0 : AdaptiveSettings::units((string) $snapshot->settings['recovery_penalty_percent']);
        $correct = [];
        $decisions = AdaptiveDecision::where('level_id', $previous->id)->get()->keyBy('id');
        foreach (AdaptiveResponse::where('level_id', $previous->id)->whereNotNull('committed_at')->where('is_correct', true)->get() as $response) {
            $key = $decisions->get($response->decision_id)?->decision['area_key'] ?? null;
            if ($key !== null) {
                $correct[$key] = ($correct[$key] ?? 0) + 1;
            }
        }
        $balances = AdaptiveAreaBalance::where('progression_id', $progression->id)
            ->whereIn('mastery', ['weak', 'untested', 'insufficient_evidence'])->orderBy('area_key')->get();
        if (! $practice) {
            $balances = $balances->where('recoverable_units', '>', 0);
        }
        $plan = $shares = $weakness = [];
        $incoming = $failedTotal = 0;
        foreach ($balances as $balance) {
            $key = $balance->area_key;
            $priorArea = $prior->area_plan[$key] ?? null;
            if (! $priorArea) {
                continue;
            }
            $failed = max(0, (int) $priorArea['question_count'] - ($correct[$key] ?? 0));
            $count = $failed;
            $remaining = $practice ? 0 : (int) $balance->recoverable_units;
            $denominator = max(1, (int) $priorArea['question_count']) * 10000;
            // Round the reduced per-question mark to the nearest cent.
            $unitMarks = intdiv((int) $priorArea['budget_units'] * (10000 - $rate) + intdiv($denominator, 2), $denominator);
            $budget = $practice ? 0 : min($remaining, $count * $unitMarks);
            $incoming += $remaining;
            $failedTotal += $failed;
            $shares[$key] = $remaining - $budget;
            $weakness[$key] = ['mastery' => $balance->mastery, 'evidence_count' => $balance->evidence_count,
                'remaining_units' => $remaining, 'failed_questions' => $failed,
                'next_questions' => $count, 'question_penalty' => $failed - $count];
            if ($count > 0 && ($practice || $budget > 0)) {
                $plan[$key] = ['question_count' => $count, 'budget_units' => $budget, 'topic_ids' => $priorArea['topic_ids'] ?? []];
            }
        }
        $available = array_sum(array_column($plan, 'budget_units'));

        return ['plan' => $plan, 'shares' => $shares, 'weakness' => $weakness, 'incoming' => $incoming,
            'available' => $available, 'penalty' => $incoming - $available, 'rate' => $rate,
            'failed_questions' => $failedTotal, 'question_count' => array_sum(array_column($plan, 'question_count'))];
    }
}
