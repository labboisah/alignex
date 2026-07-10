<?php

namespace App\Policies;

use App\Models\Subject;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationAccess;

class SubjectPolicy
{
    use AuthorizesOrganizationAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manageQuestionBank');
    }

    public function view(User $user, Subject $subject): bool
    {
        if ($user->isTeacher()) {
            return $user->assignedSubjects()->whereKey($subject->id)->exists();
        }

        return $this->viewAny($user) && $this->canAccessTenant($user, $subject);
    }

    public function create(User $user): bool
    {
        if ($user->isTeacher()) {
            return false;
        }

        return $this->viewAny($user);
    }

    public function update(User $user, Subject $subject): bool
    {
        return $this->view($user, $subject);
    }

    public function delete(User $user, Subject $subject): bool
    {
        return $this->view($user, $subject);
    }
}
