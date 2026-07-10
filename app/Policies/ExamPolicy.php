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

        return $this->create($user) && $this->canAccessOrganization($user, $exam);
    }

    public function delete(User $user, Exam $exam): bool
    {
        if ($user->isTeacher()) {
            return $this->teacherCanManageAssessment($user, $exam);
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
}
