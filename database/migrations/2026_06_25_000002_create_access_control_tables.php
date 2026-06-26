<?php

use App\Support\AccessControl;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(true);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->string('group')->index();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['role_id', 'permission_id']);
        });

        $now = now();

        foreach (AccessControl::roles() as $name => $role) {
            DB::table('roles')->insert([
                'name' => $name,
                'label' => $role['label'],
                'description' => $role['description'],
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (AccessControl::permissions() as $name => $permission) {
            DB::table('permissions')->insert([
                'name' => $name,
                'label' => $permission['label'],
                'group' => $permission['group'],
                'description' => $permission['description'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $roleIds = DB::table('roles')->pluck('id', 'name');
        $permissionIds = DB::table('permissions')->pluck('id', 'name');

        foreach (AccessControl::defaults() as $role => $permissions) {
            foreach ($permissions as $permission) {
                DB::table('permission_role')->insert([
                    'role_id' => $roleIds[$role],
                    'permission_id' => $permissionIds[$permission],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
