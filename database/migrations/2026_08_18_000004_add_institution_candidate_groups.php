<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_groups', function (Blueprint $table): void {
            if (! Schema::hasColumn('candidate_groups', 'institution_id')) {
                $table->foreignId('institution_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
                $table->foreignId('faculty_id')->nullable()->after('institution_id')->constrained()->nullOnDelete();
                $table->foreignId('department_id')->nullable()->after('faculty_id')->constrained()->nullOnDelete();
                $table->index(['institution_id', 'department_id', 'status'], 'candidate_groups_institution_department_idx');
            }
        });

        Schema::table('candidates', function (Blueprint $table): void {
            if (! Schema::hasColumn('candidates', 'institution_id')) {
                $table->foreignId('institution_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
                $table->foreignId('faculty_id')->nullable()->after('institution_id')->constrained()->nullOnDelete();
                $table->foreignId('department_id')->nullable()->after('faculty_id')->constrained()->nullOnDelete();
                $table->index(['institution_id', 'department_id', 'status'], 'candidates_institution_department_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table): void {
            if (Schema::hasColumn('candidates', 'institution_id')) {
                $table->dropIndex('candidates_institution_department_idx');
                $table->dropConstrainedForeignId('department_id');
                $table->dropConstrainedForeignId('faculty_id');
                $table->dropConstrainedForeignId('institution_id');
            }
        });

        Schema::table('candidate_groups', function (Blueprint $table): void {
            if (Schema::hasColumn('candidate_groups', 'institution_id')) {
                $table->dropIndex('candidate_groups_institution_department_idx');
                $table->dropConstrainedForeignId('department_id');
                $table->dropConstrainedForeignId('faculty_id');
                $table->dropConstrainedForeignId('institution_id');
            }
        });
    }
};
