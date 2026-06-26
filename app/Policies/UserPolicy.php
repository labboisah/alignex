<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationAccess;

class UserPolicy
{
    use AuthorizesOrganizationAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manageUsers');
    }

    public function view(User $user, User $model): bool
    {
        return $user->isSuperAdmin()
            || $user->id === $model->id
            || ($user->hasPermission('manageUsers') && $this->canAccessOrganization($user, $model));
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manageUsers');
    }

    public function update(User $user, User $model): bool
    {
        return $user->isSuperAdmin()
            || ($user->hasPermission('manageUsers') && $this->canAccessOrganization($user, $model));
    }

    public function delete(User $user, User $model): bool
    {
        return $user->isSuperAdmin()
            || ($user->hasPermission('manageUsers') && $this->canAccessOrganization($user, $model));
    }
}
