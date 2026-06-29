<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait AuthorizesOrganizationAccess
{
    protected function canAccessOrganization(User $user, mixed $model): bool
    {
        return $this->canAccessTenant($user, $model);
    }

    protected function canAccessTenant(User $user, mixed $model): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (isset($model->organization_id) && $user->belongsToOrganization($model->organization_id)) {
            return true;
        }

        if (isset($model->secondary_school_id) && $user->canAccessSecondarySchool($model->secondary_school_id)) {
            return true;
        }

        if (isset($model->professional_school_id) && $user->canAccessProfessionalSchool($model->professional_school_id)) {
            return true;
        }

        if (isset($model->cbt_center_id) && $user->canAccessCbtCenter($model->cbt_center_id)) {
            return true;
        }

        if (isset($model->school_id) && $user->belongsToSchool($model->school_id)) {
            return true;
        }

        if (isset($model->center_id) && $user->belongsToCenter($model->center_id)) {
            return true;
        }

        if (method_exists($model, 'subject') && $model->subject) {
            return $this->canAccessTenant($user, $model->subject);
        }

        if (method_exists($model, 'questionBank') && $model->questionBank) {
            return $this->canAccessTenant($user, $model->questionBank);
        }

        return false;
    }
}
