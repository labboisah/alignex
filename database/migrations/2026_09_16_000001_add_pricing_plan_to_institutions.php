<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('institutions')) {
            return;
        }

        if (! Schema::hasColumn('institutions', 'pricing_plan_id')) {
            Schema::table('institutions', function (Blueprint $table): void {
                $table->foreignId('pricing_plan_id')
                    ->nullable()
                    ->after('organization_id')
                    ->constrained('pricing_plans')
                    ->nullOnDelete();
            });
        }

        $fallbackPlanId = DB::table('pricing_plans')
            ->where('slug', 'enterprise')
            ->value('id')
            ?? DB::table('pricing_plans')->orderByDesc('price')->value('id');

        if (! $fallbackPlanId) {
            return;
        }

        DB::table('institutions')
            ->whereNull('pricing_plan_id')
            ->orderBy('id')
            ->chunkById(100, function ($institutions) use ($fallbackPlanId): void {
                foreach ($institutions as $institution) {
                    $planId = DB::table('organizations')
                        ->where('id', $institution->organization_id)
                        ->value('pricing_plan_id')
                        ?: $fallbackPlanId;

                    DB::table('institutions')
                        ->where('id', $institution->id)
                        ->update(['pricing_plan_id' => $planId]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('institutions') || ! Schema::hasColumn('institutions', 'pricing_plan_id')) {
            return;
        }

        Schema::table('institutions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pricing_plan_id');
        });
    }
};