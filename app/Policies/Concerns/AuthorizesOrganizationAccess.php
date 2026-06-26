<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait AuthorizesOrganizationAccess
{
    protected function canAccessOrganization(User $user, mixed $model): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return isset($model->organization_id) && $user->belongsToOrganization($model->organization_id);
    }
}
