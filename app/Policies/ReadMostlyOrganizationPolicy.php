<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationAccess;

class ReadMostlyOrganizationPolicy
{
    use AuthorizesOrganizationAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manageQuestionBank')
            || $user->hasPermission('manageExams')
            || $user->hasPermission('viewReports')
            || $user->hasPermission('viewSupervisorMonitor');
    }

    public function view(User $user, mixed $model): bool
    {
        return $this->viewAny($user) && $this->canAccessOrganization($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manageQuestionBank') || $user->hasPermission('manageExams');
    }

    public function update(User $user, mixed $model): bool
    {
        return $this->create($user) && $this->canAccessOrganization($user, $model);
    }

    public function delete(User $user, mixed $model): bool
    {
        return ($user->hasPermission('manageQuestionBank') || $user->hasPermission('manageExams'))
            && $this->canAccessOrganization($user, $model);
    }
}
