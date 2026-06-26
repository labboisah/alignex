<?php

namespace App\Policies;

use App\Models\Candidate;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationAccess;

class CandidatePolicy
{
    use AuthorizesOrganizationAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manageExams')
            || $user->hasPermission('viewSupervisorMonitor')
            || $user->hasPermission('viewReports');
    }

    public function view(User $user, Candidate $candidate): bool
    {
        return $this->viewAny($user) && $this->canAccessOrganization($user, $candidate);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manageExams');
    }

    public function update(User $user, Candidate $candidate): bool
    {
        return $this->create($user) && $this->canAccessOrganization($user, $candidate);
    }

    public function delete(User $user, Candidate $candidate): bool
    {
        return $user->hasPermission('manageExams')
            && $this->canAccessOrganization($user, $candidate);
    }
}
