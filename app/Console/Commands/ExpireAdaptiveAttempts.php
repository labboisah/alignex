<?php

namespace App\Console\Commands;

use App\Models\AdaptiveLevel;
use App\Models\AdaptiveProgression;
use App\Models\CandidateExamAttempt;
use App\Services\AdaptiveLifecycleService;
use Illuminate\Console\Command;

class ExpireAdaptiveAttempts extends Command
{
    protected $signature = 'adaptive:expire';

    protected $description = 'Finalize expired adaptive levels and close expired progression budgets';

    public function handle(AdaptiveLifecycleService $service): int
    {
        $count = 0;
        AdaptiveLevel::where(function ($q): void {
            $q->where(fn ($active) => $active->where('status', 'active')->where('due_at', '<=', now()))
                ->orWhereIn('progression_id', AdaptiveProgression::whereIn('status', ['active', 'prepared'])->where('closes_at', '<=', now())->select('id'));
        })->chunkById(100, function ($levels) use ($service, &$count): void {
            foreach ($levels as $level) {
                $attempt = CandidateExamAttempt::find($level->attempt_id);
                if ($attempt) {
                    $service->execute($attempt, 'expire');
                    $count++;
                }
            }
        });
        $this->info("Processed {$count} expired adaptive level(s).");

        return self::SUCCESS;
    }
}
