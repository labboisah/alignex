<?php

use App\Models\Exam;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('exam-monitor.{exam}', function ($user, string $examId): bool {
    $exam = Exam::query()->find($examId);

    if (! $exam || ! $user?->hasPermission('viewSupervisorMonitor')) {
        return false;
    }

    if ($user->isSuperAdmin()) {
        return true;
    }

    return ($exam->organization_id && (string) $exam->organization_id === (string) $user->organization_id)
        || ($exam->school_id && (string) $exam->school_id === (string) $user->school_id)
        || ($exam->center_id && (string) $exam->center_id === (string) $user->center_id);
});
