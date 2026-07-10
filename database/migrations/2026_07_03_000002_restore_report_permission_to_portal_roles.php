<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $permissionId = DB::table('permissions')->where('name', 'viewReports')->value('id');

        if (! $permissionId) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('name', [
                User::ROLE_SUPER_ADMIN,
                User::ROLE_ORGANIZATION_ADMIN,
                User::ROLE_CENTER_ADMIN,
                User::ROLE_CBT_CENTER_ADMIN,
                User::ROLE_SCHOOL_ADMIN,
                User::ROLE_SECONDARY_SCHOOL_ADMIN,
                User::ROLE_PROFESSIONAL_SCHOOL_ADMIN,
                User::ROLE_EXAMINER,
                User::ROLE_SUPERVISOR,
            ])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('permission_role')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $permissionId],
                []
            );
        }
    }

    public function down(): void
    {
        //
    }
};
