<?php

namespace App\Services;

use App\Models\Exam;

class ExamStatusService
{
    public function sync(Exam $exam): Exam
    {
        if ($this->shouldComplete($exam)) {
            $exam->forceFill(['status' => Exam::STATUS_COMPLETED])->save();
        }

        return $exam->refresh();
    }

    public function syncOverdue(): int
    {
        return Exam::query()
            ->whereIn('status', [Exam::STATUS_ACTIVE, Exam::STATUS_SCHEDULED])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->update(['status' => Exam::STATUS_COMPLETED, 'updated_at' => now()]);
    }

    public function shouldComplete(Exam $exam): bool
    {
        return in_array($exam->status, [Exam::STATUS_ACTIVE, Exam::STATUS_SCHEDULED], true)
            && $exam->ends_at !== null
            && $exam->ends_at->isPast();
    }
}
