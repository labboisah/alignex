<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;

class SchoolPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manageSchools');
    }

    public function view(User $user, School $school): bool
    {
        return $user->isSuperAdmin()
            || ($user->isSchoolAdmin() && $user->belongsToSchool($school->id));
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() && $user->hasPermission('manageSchools');
    }

    public function update(User $user, School $school): bool
    {
        return $user->isSuperAdmin()
            || ($user->isSchoolAdmin() && $user->hasPermission('manageSchools') && $user->belongsToSchool($school->id));
    }

    public function delete(User $user, School $school): bool
    {
        return $user->isSuperAdmin() && $user->hasPermission('manageSchools');
    }

    public function deactivate(User $user, School $school): bool
    {
        return $this->update($user, $school);
    }
}
