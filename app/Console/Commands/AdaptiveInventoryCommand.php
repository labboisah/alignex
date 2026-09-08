<?php

namespace App\Console\Commands;

use App\Models\CandidateExamAttempt;
use App\Models\Exam;
use App\Services\AdaptiveRolloutService;
use Illuminate\Console\Command;

class AdaptiveInventoryCommand extends Command
{
    protected $signature = 'adaptive:inventory {--json : Output machine-readable inventory without candidate data}';

    protected $description = 'Read-only inventory of adaptive-labelled exams and legacy attempt handling';

    public function handle(AdaptiveRolloutService $rollout): int
    {
        $rows = Exam::query()
            ->where(fn ($query) => $query->where('mode', Exam::MODE_ADAPTIVE)->orWhere('exam_mode', Exam::MODE_ADAPTIVE))
            ->withCount([
                'attempts',
                'attempts as unstarted_count' => fn ($query) => $query->whereNull('started_at')->where('status', CandidateExamAttempt::STATUS_NOT_STARTED),
                'attempts as resumable_count' => fn ($query) => $query->whereNotNull('started_at')->where('status', CandidateExamAttempt::STATUS_IN_PROGRESS),
            ])
            ->orderBy('id')
            ->get()
            ->map(fn (Exam $exam) => [
                'exam_id' => $exam->id,
                'owner' => $rollout->ownerKey($exam),
                'status' => $exam->status,
                'mode' => $exam->mode,
                'exam_mode' => $exam->exam_mode,
                'mode_conflict' => $exam->exam_mode !== null && $exam->mode !== $exam->exam_mode,
                'attempts' => $exam->attempts_count,
                'blocked_unstarted' => $exam->unstarted_count,
                'preserved_in_progress' => $exam->resumable_count,
                'handling' => 'No migration; block new starts; preserve started papers and historical results.',
            ])->all();

        if ($this->option('json')) {
            $this->line(json_encode(['runtime_ready' => false, 'exam_count' => count($rows), 'exams' => $rows], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } elseif ($rows === []) {
            $this->info('No adaptive-labelled exams found.');
        } else {
            $this->table(['Exam', 'Owner', 'Status', 'Unstarted (blocked)', 'In progress (preserved)', 'Mode conflict'], array_map(fn ($row) => [
                $row['exam_id'], $row['owner'], $row['status'], $row['blocked_unstarted'], $row['preserved_in_progress'], $row['mode_conflict'] ? 'yes' : 'no',
            ], $rows));
        }

        return self::SUCCESS;
    }
}
