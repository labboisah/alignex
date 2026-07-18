<?php

namespace App\Policies;

use App\Models\Exam;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationAccess;

class ExamPolicy
{
    use AuthorizesOrganizationAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manageExams') || $user->hasPermission('viewSupervisorMonitor');
    }

    public function view(User $user, Exam $exam): bool
    {
        if ($user->isTeacher()) {
            return $exam->examSubjects()
                ->whereIn('subject_id', $user->assignedSubjects()->select('subjects.id'))
                ->exists();
        }

        if ($user->isFacilitator()) {
            return $this->facilitatorCanViewAssessment($user, $exam);
        }

        return $this->viewAny($user) && $this->canAccessOrganization($user, $exam);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manageExams');
    }

    public function update(User $user, Exam $exam): bool
    {
        if ($user->isTeacher()) {
            return $this->teacherCanManageAssessment($user, $exam);
        }

        if ($user->isFacilitator()) {
            return $this->facilitatorCanManageAssessment($user, $exam);
        }

        return $this->create($user) && $this->canAccessOrganization($user, $exam);
    }

    public function delete(User $user, Exam $exam): bool
    {
        if ($user->isTeacher()) {
            return $this->teacherCanManageAssessment($user, $exam);
        }

        if ($user->isFacilitator()) {
            return $this->facilitatorCanManageAssessment($user, $exam);
        }

        return $user->hasPermission('manageExams')
            && $this->canAccessOrganization($user, $exam);
    }

    private function teacherCanManageAssessment(User $user, Exam $exam): bool
    {
        return $exam->exam_category === Exam::CATEGORY_ASSESSMENT
            && (string) $exam->created_by === (string) $user->id
            && $this->view($user, $exam);
    }

    private function facilitatorCanViewAssessment(User $user, Exam $exam): bool
    {
        return $this->viewAny($user)
            && $exam->exam_category === Exam::CATEGORY_ASSESSMENT
            && (string) $exam->professional_school_id === (string) $user->professional_school_id
            && $exam->examSubjects()
                ->whereHas('questionBank', function ($query) use ($user): void {
                    $query
                        ->whereIn('course_id', $user->assignedCourses()->select('courses.id'))
                        ->orWhereIn('module_id', $user->assignedModules()->select('modules.id'));
                })
                ->exists();
    }

    private function facilitatorCanManageAssessment(User $user, Exam $exam): bool
    {
        return $exam->exam_category === Exam::CATEGORY_ASSESSMENT
            && (string) $exam->created_by === (string) $user->id
            && $this->facilitatorCanViewAssessment($user, $exam);
    }
}
