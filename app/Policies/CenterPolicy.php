<?php

namespace App\Policies;

use App\Models\Center;
use App\Models\User;

class CenterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manageCenters');
    }

    public function view(User $user, Center $center): bool
    {
        return $user->isSuperAdmin()
            || ($user->isCenterAdmin() && $user->belongsToCenter($center->id));
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() && $user->hasPermission('manageCenters');
    }

    public function update(User $user, Center $center): bool
    {
        return $user->isSuperAdmin()
            || ($user->isCenterAdmin() && $user->hasPermission('manageCenters') && $user->belongsToCenter($center->id));
    }

    public function delete(User $user, Center $center): bool
    {
        return $user->isSuperAdmin() && $user->hasPermission('manageCenters');
    }

    public function deactivate(User $user, Center $center): bool
    {
        return $this->update($user, $center);
    }
}
