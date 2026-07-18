<?php

namespace App\Services;

use App\Models\PricingPlan;
use App\Models\User;
use App\Support\PlanFeatures;
use Illuminate\Database\Eloquent\Model;

class PlanFeatureService
{
    public function planForUser(User $user): ?PricingPlan
    {
        if ($user->isSuperAdmin()) {
            return null;
        }

        $owner = $this->ownerForUser($user);

        return $this->planForOwner($owner);
    }

    public function hasFeature(User $user, string $feature): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $plan = $this->planForUser($user);

        if (! $plan) {
            return false;
        }

        return (bool) data_get($plan->features ?? [], $feature, false);
    }

    /**
     * @return array<string, bool>
     */
    public function featuresForOwner(?Model $owner): array
    {
        $features = $this->planForOwner($owner)?->features ?? [];

        return collect(PlanFeatures::labels())
            ->map(fn (string $label, string $key): bool => (bool) ($features[$key] ?? false))
            ->all();
    }

    /**
     * @return array<string, bool>
     */
    public function featuresForUser(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return collect(PlanFeatures::labels())
                ->map(fn (): bool => true)
                ->all();
        }

        $features = $this->planForUser($user)?->features ?? [];

        return collect(PlanFeatures::labels())
            ->map(fn (string $label, string $key): bool => (bool) ($features[$key] ?? false))
            ->all();
    }

    /**
     * @return array{id: int|null, slug: string|null, name: string|null}
     */
    public function planSummaryForUser(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return ['id' => null, 'slug' => 'super_admin', 'name' => 'Super Admin'];
        }

        $plan = $this->planForUser($user);

        return [
            'id' => $plan?->id,
            'slug' => $plan?->slug,
            'name' => $plan?->name,
        ];
    }

    /**
     * @return array{id: int|null, slug: string|null, name: string|null}
     */
    public function planSummaryForOwner(?Model $owner): array
    {
        $plan = $this->planForOwner($owner);

        return [
            'id' => $plan?->id,
            'slug' => $plan?->slug,
            'name' => $plan?->name,
        ];
    }

    private function ownerForUser(User $user): ?Model
    {
        return match (true) {
            $user->organization_id !== null => $user->organization,
            $user->secondary_school_id !== null => $user->secondarySchool,
            $user->professional_school_id !== null => $user->professionalSchool,
            $user->cbt_center_id !== null => $user->cbtCenter,
            $user->school_id !== null => $user->school,
            $user->center_id !== null => $user->center,
            default => null,
        };
    }

    private function planForOwner(?Model $owner): ?PricingPlan
    {
        if (! $owner) {
            return null;
        }

        $owner->loadMissing('pricingPlan');

        $plan = $owner->getRelation('pricingPlan');

        if ($plan) {
            return $plan;
        }

        if (method_exists($owner, 'organization')) {
            $owner->loadMissing('organization.pricingPlan');

            return $owner->organization?->pricingPlan;
        }

        return null;
    }
}
