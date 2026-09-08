<?php

namespace Tests;

use App\Models\PricingPlan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function grantPlanFeatures(Model $owner, array $features): void
    {
        $plan = PricingPlan::query()->create([
            'name' => 'Workflow test plan',
            'description' => 'Explicit feature entitlement for workflow tests.',
            'slug' => 'workflow-'.Str::uuid(),
            'price' => 0,
            'currency' => 'NGN',
            'billing_cycle' => 'monthly',
            'features' => array_fill_keys($features, true),
            'is_active' => true,
        ]);
        $owner->forceFill(['pricing_plan_id' => $plan->id])->save();
    }
}
