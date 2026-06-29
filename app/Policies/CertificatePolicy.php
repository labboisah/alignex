<?php

namespace App\Policies;

use App\Models\Certificate;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationAccess;

class CertificatePolicy
{
    use AuthorizesOrganizationAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('viewReports') || $user->hasPermission('manageExams');
    }

    public function view(User $user, Certificate $certificate): bool
    {
        return $this->viewAny($user) && $this->canAccessTenant($user, $certificate);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manageExams');
    }

    public function update(User $user, Certificate $certificate): bool
    {
        return $this->create($user) && $this->canAccessTenant($user, $certificate);
    }
}
