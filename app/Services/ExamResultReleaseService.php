<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ExamResultReleaseService
{
    public function setReleased(Exam $exam, bool $released, User $actor): void
    {
        DB::transaction(function () use ($exam, $released, $actor): void {
            $exam = Exam::whereKey($exam->id)->lockForUpdate()->firstOrFail();
            abort_if(app(AdaptiveRolloutService::class)->isAdaptive($exam) || app(AdaptiveReportService::class)->hasProgressions($exam), 422, 'Use the adaptive result release workflow.');
            $exam->update([
                'settings' => array_merge($exam->settings ?? [], ['show_result_immediately' => $released]),
                'result_release_settings' => array_merge($exam->result_release_settings ?? [], ['release_mode' => $released ? 'released' : 'held']),
            ]);
            $exam->auditLogs()->create([
                'actor_user_id' => $actor->id, 'actor_type' => 'user',
                'event_type' => $released ? 'results_released' : 'results_held',
                'description' => $released ? 'Candidate results released for this exam.' : 'Candidate results held for this exam.',
                'metadata' => ['released' => $released], 'occurred_at' => now(),
            ]);
        });
    }
}
