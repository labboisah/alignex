<?php

namespace App\Jobs;

use App\Models\Exam;
use App\Services\ExamPaperGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateExamPapers implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1200;

    public int $uniqueFor = 1800;

    public function __construct(public readonly string $examId)
    {
        $this->onQueue('paper-generation');
    }

    public function uniqueId(): string
    {
        return 'exam-paper-generation:'.$this->examId;
    }

    public function handle(ExamPaperGeneratorService $generator): void
    {
        $exam = Exam::query()->find($this->examId);

        if (! $exam) {
            return;
        }

        $generator->generate($exam);
    }
}