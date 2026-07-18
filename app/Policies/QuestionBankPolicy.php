<?php

namespace App\Policies;

use App\Models\QuestionBank;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationAccess;

class QuestionBankPolicy
{
    use AuthorizesOrganizationAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manageQuestionBank');
    }

    public function view(User $user, QuestionBank $questionBank): bool
    {
        if ($user->isTeacher()) {
            return $questionBank->subject_id !== null
                && $user->assignedSubjects()->whereKey($questionBank->subject_id)->exists();
        }

        if ($user->isFacilitator()) {
            return $this->facilitatorCanAccess($user, $questionBank);
        }

        return $this->viewAny($user) && $this->canAccessTenant($user, $questionBank);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, QuestionBank $questionBank): bool
    {
        return $this->view($user, $questionBank);
    }

    public function delete(User $user, QuestionBank $questionBank): bool
    {
        if ($user->isTeacher()) {
            return $this->view($user, $questionBank);
        }

        return $user->hasPermission('manageQuestionBank')
            && $this->canAccessTenant($user, $questionBank);
    }

    private function facilitatorCanAccess(User $user, QuestionBank $questionBank): bool
    {
        return $this->viewAny($user)
            && (string) $questionBank->professional_school_id === (string) $user->professional_school_id
            && (
                ($questionBank->course_id && $user->assignedCourses()->whereKey($questionBank->course_id)->exists())
                || ($questionBank->module_id && $user->assignedModules()->whereKey($questionBank->module_id)->exists())
            );
    }
}
