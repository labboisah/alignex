<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\AccessControl;
use Illuminate\Support\Facades\DB;

class AccessControlService
{
    /**
     * @param  array<string, array<int, string>>  $assignments
     */
    public function syncRolePermissions(array $assignments): void
    {
        DB::transaction(function () use ($assignments): void {
            $permissionIds = Permission::query()->pluck('id', 'name');

            Role::query()
                ->with('permissions')
                ->get()
                ->each(function (Role $role) use ($assignments, $permissionIds): void {
                    $permissionNames = $assignments[$role->name] ?? $role->permissions->pluck('name')->all();

                    if ($role->name === User::ROLE_SUPER_ADMIN) {
                        $permissionNames = array_keys(AccessControl::permissions());
                    }

                    if ($role->name === User::ROLE_CANDIDATE) {
                        $permissionNames = [];
                    }

                    $role->permissions()->sync(
                        collect($permissionNames)
                            ->intersect($permissionIds->keys())
                            ->map(fn (string $name) => $permissionIds[$name])
                            ->values()
                            ->all()
                    );
                });
        });
    }
}
