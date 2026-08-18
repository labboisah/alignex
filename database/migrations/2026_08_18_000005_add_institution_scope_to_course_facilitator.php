<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_facilitator', function (Blueprint $table): void {
            if (Schema::hasColumn('course_facilitator', 'professional_school_id')) {
                $table->foreignId('professional_school_id')->nullable()->change();
            }

            if (! Schema::hasColumn('course_facilitator', 'institution_id')) {
                $table->foreignId('institution_id')->nullable()->after('professional_school_id')->constrained()->cascadeOnDelete();
                $table->foreignId('faculty_id')->nullable()->after('institution_id')->constrained()->cascadeOnDelete();
                $table->foreignId('department_id')->nullable()->after('faculty_id')->constrained()->cascadeOnDelete();
                $table->index(['institution_id', 'department_id', 'course_id'], 'course_facilitator_institution_department_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('course_facilitator', function (Blueprint $table): void {
            if (Schema::hasColumn('course_facilitator', 'institution_id')) {
                $table->dropIndex('course_facilitator_institution_department_index');
                $table->dropConstrainedForeignId('department_id');
                $table->dropConstrainedForeignId('faculty_id');
                $table->dropConstrainedForeignId('institution_id');
            }

            if (Schema::hasColumn('course_facilitator', 'professional_school_id')) {
                $table->foreignId('professional_school_id')->nullable(false)->change();
            }
        });
    }
};
