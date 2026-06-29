<?php

namespace App\Policies;

use App\Models\SecondarySchool;
use App\Models\User;

class SecondarySchoolPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manageSchools') || $user->hasPermission('viewReports');
    }

    public function view(User $user, SecondarySchool $secondarySchool): bool
    {
        return $this->viewAny($user) && $user->canAccessSecondarySchool($secondarySchool->id);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() && $user->hasPermission('manageSchools');
    }

    public function update(User $user, SecondarySchool $secondarySchool): bool
    {
        return $user->hasPermission('manageSchools') && $user->canAccessSecondarySchool($secondarySchool->id);
    }

    public function delete(User $user, SecondarySchool $secondarySchool): bool
    {
        return $user->isSuperAdmin() && $this->update($user, $secondarySchool);
    }
}
