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
        if ($user->isInstitutionLecturer()) {
            return $this->viewAny($user)
                && (string) $candidate->institution_id === (string) $user->institution_id
                && (string) $candidate->department_id === (string) $user->department_id;
        }

        return $this->viewAny($user) && $this->canAccessOrganization($user, $candidate);
    }

    public function create(User $user): bool
    {
        if ($user->isInstitutionLecturer()) {
            return false;
        }

        return $user->hasPermission('manageExams');
    }

    public function update(User $user, Candidate $candidate): bool
    {
        return $this->create($user) && $this->canAccessOrganization($user, $candidate);
    }

    public function delete(User $user, Candidate $candidate): bool
    {
        if ($user->isInstitutionLecturer()) {
            return false;
        }

        return $user->hasPermission('manageExams')
            && $this->canAccessOrganization($user, $candidate);
    }
}
