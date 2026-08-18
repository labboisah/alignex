<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table): void {
            if (! Schema::hasColumn('exams', 'institution_id')) {
                $table->foreignId('institution_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
                $table->foreignId('faculty_id')->nullable()->after('institution_id')->constrained()->nullOnDelete();
                $table->foreignId('department_id')->nullable()->after('faculty_id')->constrained()->nullOnDelete();
                $table->index(['institution_id', 'status'], 'exams_institution_status_idx');
            }
        });

        Schema::table('question_banks', function (Blueprint $table): void {
            $table->foreignUlid('subject_id')->nullable()->change();
        });

        Schema::table('questions', function (Blueprint $table): void {
            $table->foreignUlid('subject_id')->nullable()->change();
        });

        Schema::table('exam_subjects', function (Blueprint $table): void {
            $table->foreignUlid('subject_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('exam_subjects', function (Blueprint $table): void {
            $table->foreignUlid('subject_id')->nullable(false)->change();
        });

        Schema::table('questions', function (Blueprint $table): void {
            $table->foreignUlid('subject_id')->nullable(false)->change();
        });

        Schema::table('question_banks', function (Blueprint $table): void {
            $table->foreignUlid('subject_id')->nullable(false)->change();
        });

        Schema::table('exams', function (Blueprint $table): void {
            if (Schema::hasColumn('exams', 'institution_id')) {
                $table->dropIndex('exams_institution_status_idx');
                $table->dropConstrainedForeignId('department_id');
                $table->dropConstrainedForeignId('faculty_id');
                $table->dropConstrainedForeignId('institution_id');
            }
        });
    }
};
