<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            $table->foreignId('center_id')->nullable()->after('school_id')->constrained()->nullOnDelete();
            $table->unique(['center_id', 'code'], 'subjects_center_code_unique');
        });

        Schema::table('question_banks', function (Blueprint $table): void {
            $table->foreignId('center_id')->nullable()->after('school_id')->constrained()->nullOnDelete();
            $table->unique(['center_id', 'code'], 'question_banks_center_code_unique');
        });

        $permissionId = DB::table('permissions')->where('name', 'manageQuestionBank')->value('id');
        $roleIds = DB::table('roles')
            ->whereIn('name', [User::ROLE_SCHOOL_ADMIN, User::ROLE_CENTER_ADMIN])
            ->pluck('id');

        if ($permissionId) {
            foreach ($roleIds as $roleId) {
                DB::table('permission_role')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => $permissionId],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::table('question_banks', function (Blueprint $table): void {
            $table->dropUnique('question_banks_center_code_unique');
            $table->dropConstrainedForeignId('center_id');
        });

        Schema::table('subjects', function (Blueprint $table): void {
            $table->dropUnique('subjects_center_code_unique');
            $table->dropConstrainedForeignId('center_id');
        });
    }
};
