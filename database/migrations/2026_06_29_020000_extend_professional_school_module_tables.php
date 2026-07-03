<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programmes', function (Blueprint $table): void {
            if (! Schema::hasColumn('programmes', 'duration')) {
                $table->string('duration')->nullable()->after('description');
            }
        });

        Schema::table('candidates', function (Blueprint $table): void {
            if (! Schema::hasColumn('candidates', 'programme_id')) {
                $table->foreignId('programme_id')->nullable()->after('professional_school_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('candidates', 'course_id')) {
                $table->foreignId('course_id')->nullable()->after('programme_id')->constrained()->nullOnDelete();
            }
        });

        Schema::table('exams', function (Blueprint $table): void {
            if (! Schema::hasColumn('exams', 'programme_id')) {
                $table->foreignId('programme_id')->nullable()->after('subject_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('exams', 'course_id')) {
                $table->foreignId('course_id')->nullable()->after('programme_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('exams', 'module_id')) {
                $table->foreignId('module_id')->nullable()->after('course_id')->constrained('modules')->nullOnDelete();
            }
            if (! Schema::hasColumn('exams', 'training_batch_id')) {
                $table->foreignId('training_batch_id')->nullable()->after('module_id')->constrained()->nullOnDelete();
            }
        });

        Schema::table('question_banks', function (Blueprint $table): void {
            if (Schema::hasColumn('question_banks', 'subject_id')) {
                $table->foreignUlid('subject_id')->nullable()->change();
            }
        });

        Schema::table('certificates', function (Blueprint $table): void {
            if (! Schema::hasColumn('certificates', 'organization_id')) {
                $table->foreignId('organization_id')->nullable()->after('exam_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('certificates', 'verification_code')) {
                $table->string('verification_code')->nullable()->after('verification_hash')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table): void {
            if (Schema::hasColumn('certificates', 'verification_code')) {
                $table->dropColumn('verification_code');
            }
            if (Schema::hasColumn('certificates', 'organization_id')) {
                $table->dropConstrainedForeignId('organization_id');
            }
        });

        Schema::table('exams', function (Blueprint $table): void {
            foreach (['training_batch_id', 'module_id', 'course_id', 'programme_id'] as $column) {
                if (Schema::hasColumn('exams', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
        });

        Schema::table('candidates', function (Blueprint $table): void {
            foreach (['course_id', 'programme_id'] as $column) {
                if (Schema::hasColumn('candidates', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
        });

        Schema::table('programmes', function (Blueprint $table): void {
            if (Schema::hasColumn('programmes', 'duration')) {
                $table->dropColumn('duration');
            }
        });
    }
};
