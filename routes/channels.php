<?php

use App\Models\Exam;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('exam-monitor.{exam}', function ($user, string $examId): bool {
    $exam = Exam::query()->find($examId);

    if (! $exam || (! $user?->hasPermission('viewSupervisorMonitor') && ! $user?->hasPermission('manageExams'))) {
        return false;
    }

    if ($user->isSuperAdmin()) {
        return true;
    }

    if ($user->isFacilitator()) {
        return $exam->exam_category === Exam::CATEGORY_ASSESSMENT
            && ($exam->institution_id || $exam->professional_school_id)
            && $exam->examSubjects()
                ->whereHas('questionBank', fn ($query) => $query
                    ->whereIn('course_id', $user->assignedCourses()->select('courses.id'))
                    ->orWhereIn('module_id', $user->assignedModules()->select('modules.id')))
                ->exists();
    }

    if ($user->isTeacher()) {
        return $exam->exam_category === Exam::CATEGORY_ASSESSMENT
            && $exam->examSubjects()
                ->whereIn('subject_id', $user->assignedSubjects()->select('subjects.id'))
                ->exists();
    }

    return ($exam->organization_id && (string) $exam->organization_id === (string) $user->organization_id)
        || ($exam->institution_id && (string) $exam->institution_id === (string) $user->institution_id)
        || ($exam->school_id && (string) $exam->school_id === (string) $user->school_id)
        || ($exam->center_id && (string) $exam->center_id === (string) $user->center_id);
});
