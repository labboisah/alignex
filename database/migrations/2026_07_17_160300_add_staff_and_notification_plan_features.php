<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pricing_plans')) {
            return;
        }

        $plans = DB::table('pricing_plans')->get(['id', 'slug', 'features']);

        foreach ($plans as $plan) {
            $features = json_decode($plan->features ?? '[]', true) ?: [];

            $features['teacher_management'] = in_array($plan->slug, ['standard', 'professional', 'enterprise'], true);
            $features['facilitator_management'] = in_array($plan->slug, ['professional', 'enterprise'], true);
            $features['email_notifications'] = in_array($plan->slug, ['standard', 'professional', 'enterprise'], true);
            $features['sms_notifications'] = in_array($plan->slug, ['professional', 'enterprise'], true);

            DB::table('pricing_plans')
                ->where('id', $plan->id)
                ->update([
                    'features' => json_encode($features),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('pricing_plans')) {
            return;
        }

        $plans = DB::table('pricing_plans')->get(['id', 'features']);

        foreach ($plans as $plan) {
            $features = json_decode($plan->features ?? '[]', true) ?: [];

            unset(
                $features['teacher_management'],
                $features['facilitator_management'],
                $features['email_notifications'],
                $features['sms_notifications'],
            );

            DB::table('pricing_plans')
                ->where('id', $plan->id)
                ->update([
                    'features' => json_encode($features),
                    'updated_at' => now(),
                ]);
        }
    }
};
