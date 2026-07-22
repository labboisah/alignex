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
            $features['offline_question_authoring'] = in_array($plan->slug, ['professional', 'enterprise'], true);

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
            unset($features['offline_question_authoring']);

            DB::table('pricing_plans')
                ->where('id', $plan->id)
                ->update([
                    'features' => json_encode($features),
                    'updated_at' => now(),
                ]);
        }
    }
};
