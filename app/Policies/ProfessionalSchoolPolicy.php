<?php

namespace App\Policies;

use App\Models\ProfessionalSchool;
use App\Models\User;

class ProfessionalSchoolPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manageSchools') || $user->hasPermission('viewReports');
    }

    public function view(User $user, ProfessionalSchool $professionalSchool): bool
    {
        return $this->viewAny($user) && $user->canAccessProfessionalSchool($professionalSchool->id);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() && $user->hasPermission('manageSchools');
    }

    public function update(User $user, ProfessionalSchool $professionalSchool): bool
    {
        return $user->hasPermission('manageSchools') && $user->canAccessProfessionalSchool($professionalSchool->id);
    }

    public function delete(User $user, ProfessionalSchool $professionalSchool): bool
    {
        return $user->isSuperAdmin() && $this->update($user, $professionalSchool);
    }
}
