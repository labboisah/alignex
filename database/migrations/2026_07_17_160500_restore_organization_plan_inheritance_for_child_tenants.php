<?php

use App\Models\AdminRegistrationRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private array $childTables = [
        AdminRegistrationRequest::TYPE_SECONDARY_SCHOOL => 'secondary_schools',
        AdminRegistrationRequest::TYPE_PROFESSIONAL_SCHOOL => 'professional_schools',
        AdminRegistrationRequest::TYPE_CBT_CENTER => 'cbt_centers',
    ];

    public function up(): void
    {
        foreach ($this->childTables as $entityType => $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'pricing_plan_id') || ! Schema::hasColumn($table, 'organization_id')) {
                continue;
            }

            $directRegistrationIds = DB::table('admin_registration_requests')
                ->where('entity_type', $entityType)
                ->whereNotNull('entity_id')
                ->whereNotNull('pricing_plan_id')
                ->pluck('entity_id')
                ->all();

            DB::table($table)
                ->whereNotNull('organization_id')
                ->when($directRegistrationIds !== [], fn ($query) => $query->whereNotIn('id', $directRegistrationIds))
                ->update(['pricing_plan_id' => null]);
        }
    }

    public function down(): void
    {
        $fallbackPlanId = DB::table('pricing_plans')
            ->where('slug', 'enterprise')
            ->value('id')
            ?? DB::table('pricing_plans')->orderByDesc('price')->value('id');

        if (! $fallbackPlanId) {
            return;
        }

        foreach ($this->childTables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'pricing_plan_id')) {
                continue;
            }

            DB::table($table)
                ->whereNull('pricing_plan_id')
                ->update(['pricing_plan_id' => $fallbackPlanId]);
        }
    }
};
