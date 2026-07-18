<?php

use App\Models\AdminRegistrationRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private array $tenantTables = [
        AdminRegistrationRequest::TYPE_ORGANIZATION => 'organizations',
        AdminRegistrationRequest::TYPE_SCHOOL => 'schools',
        AdminRegistrationRequest::TYPE_SECONDARY_SCHOOL => 'secondary_schools',
        AdminRegistrationRequest::TYPE_PROFESSIONAL_SCHOOL => 'professional_schools',
        AdminRegistrationRequest::TYPE_CENTER => 'centers',
        AdminRegistrationRequest::TYPE_CBT_CENTER => 'cbt_centers',
    ];

    public function up(): void
    {
        foreach ($this->tenantTables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'pricing_plan_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $table): void {
                $table->foreignId('pricing_plan_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('pricing_plans')
                    ->nullOnDelete();
            });
        }

        $fallbackPlanId = DB::table('pricing_plans')
            ->where('slug', 'enterprise')
            ->value('id')
            ?? DB::table('pricing_plans')->orderByDesc('price')->value('id');

        foreach ($this->tenantTables as $entityType => $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'pricing_plan_id')) {
                continue;
            }

            DB::table('admin_registration_requests')
                ->where('entity_type', $entityType)
                ->whereNotNull('entity_id')
                ->whereNotNull('pricing_plan_id')
                ->orderBy('id')
                ->chunkById(100, function ($registrations) use ($table): void {
                    foreach ($registrations as $registration) {
                        DB::table($table)
                            ->where('id', $registration->entity_id)
                            ->update(['pricing_plan_id' => $registration->pricing_plan_id]);
                    }
                });

            if ($fallbackPlanId) {
                $query = DB::table($table)->whereNull('pricing_plan_id');

                if (in_array($table, ['secondary_schools', 'professional_schools', 'cbt_centers'], true)) {
                    $query->whereNull('organization_id');
                }

                $query->update(['pricing_plan_id' => $fallbackPlanId]);
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tenantTables) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'pricing_plan_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('pricing_plan_id');
            });
        }
    }
};
