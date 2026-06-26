<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manageOrganizations');
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->hasPermission('manageOrganizations') || $user->belongsToOrganization($organization->id);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() && $user->hasPermission('manageOrganizations');
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->isSuperAdmin() && $user->hasPermission('manageOrganizations');
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $user->isSuperAdmin() && $user->hasPermission('manageOrganizations');
    }

    public function deactivate(User $user, Organization $organization): bool
    {
        return $this->update($user, $organization);
    }
}
