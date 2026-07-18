<?php

use App\Support\AccessControl;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $permission = AccessControl::permissions()['downloadOfflineServer'];
        $now = now();

        $existingPermission = DB::table('permissions')->where('name', 'downloadOfflineServer')->first();

        DB::table('permissions')->updateOrInsert(
            ['name' => 'downloadOfflineServer'],
            [
                'label' => $permission['label'],
                'group' => $permission['group'],
                'description' => $permission['description'],
                'created_at' => $existingPermission?->created_at ?? $now,
                'updated_at' => $now,
            ],
        );

        $permissionId = DB::table('permissions')->where('name', 'downloadOfflineServer')->value('id');

        foreach (AccessControl::defaults() as $role => $permissions) {
            if (! in_array('downloadOfflineServer', $permissions, true)) {
                continue;
            }

            $roleId = DB::table('roles')->where('name', $role)->value('id');

            if (! $roleId || ! $permissionId) {
                continue;
            }

            DB::table('permission_role')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $permissionId],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $permissionId = DB::table('permissions')->where('name', 'downloadOfflineServer')->value('id');

        if ($permissionId && Schema::hasTable('permission_role')) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        }

        DB::table('permissions')->where('name', 'downloadOfflineServer')->delete();
    }
};
