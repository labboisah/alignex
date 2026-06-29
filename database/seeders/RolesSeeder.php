<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\AccessControl;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (AccessControl::permissions() as $name => $permission) {
            Permission::query()->updateOrCreate(
                ['name' => $name],
                [
                    'label' => $permission['label'],
                    'group' => $permission['group'],
                    'description' => $permission['description'],
                ]
            );
        }

        $permissionIds = Permission::query()->pluck('id', 'name');

        foreach (AccessControl::roles() as $name => $role) {
            $record = Role::query()->updateOrCreate(
                ['name' => $name],
                [
                    'label' => $role['label'],
                    'description' => $role['description'],
                    'is_system' => true,
                ]
            );

            $record->permissions()->sync(
                collect(AccessControl::defaults()[$name] ?? [])
                    ->map(fn (string $permission) => $permissionIds[$permission] ?? null)
                    ->filter()
                    ->values()
                    ->all()
            );
        }
    }
}
