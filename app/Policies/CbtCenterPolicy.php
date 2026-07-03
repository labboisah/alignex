<?php

namespace App\Policies;

use App\Models\CbtCenter;
use App\Models\User;

class CbtCenterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manageCenters') || $user->hasPermission('viewReports');
    }

    public function view(User $user, CbtCenter $cbtCenter): bool
    {
        return $this->viewAny($user) && (
            $user->canAccessCbtCenter($cbtCenter->id)
            || $user->canAccessOrganization($cbtCenter->organization_id)
        );
    }

    public function create(User $user): bool
    {
        return ($user->isSuperAdmin() || $user->isOrganizationAdmin()) && $user->hasPermission('manageCenters');
    }

    public function update(User $user, CbtCenter $cbtCenter): bool
    {
        return $user->hasPermission('manageCenters') && (
            $user->canAccessCbtCenter($cbtCenter->id)
            || $user->canAccessOrganization($cbtCenter->organization_id)
        );
    }

    public function delete(User $user, CbtCenter $cbtCenter): bool
    {
        return $user->isSuperAdmin() && $this->update($user, $cbtCenter);
    }
}
