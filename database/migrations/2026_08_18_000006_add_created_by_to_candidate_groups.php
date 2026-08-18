<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_groups', function (Blueprint $table): void {
            if (! Schema::hasColumn('candidate_groups', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('cbt_center_id')->constrained('users')->nullOnDelete();
                $table->index(['created_by', 'department_id'], 'candidate_groups_creator_department_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('candidate_groups', function (Blueprint $table): void {
            if (Schema::hasColumn('candidate_groups', 'created_by')) {
                $table->dropIndex('candidate_groups_creator_department_idx');
                $table->dropConstrainedForeignId('created_by');
            }
        });
    }
};
