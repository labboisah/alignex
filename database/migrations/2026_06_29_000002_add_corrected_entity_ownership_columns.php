<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            if (! Schema::hasColumn('organizations', 'organization_type')) {
                $table->string('organization_type')->nullable();
            }
            if (! Schema::hasColumn('organizations', 'description')) {
                $table->text('description')->nullable();
            }
            if (! Schema::hasColumn('organizations', 'logo')) {
                $table->string('logo')->nullable();
            }
            if (! Schema::hasColumn('organizations', 'website')) {
                $table->string('website')->nullable();
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'secondary_school_id')) {
                $table->foreignId('secondary_school_id')->nullable()->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'professional_school_id')) {
                $table->foreignId('professional_school_id')->nullable()->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'cbt_center_id')) {
                $table->foreignId('cbt_center_id')->nullable()->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'active_context_type')) {
                $table->string('active_context_type')->nullable();
            }
            if (! Schema::hasColumn('users', 'active_context_id')) {
                $table->unsignedBigInteger('active_context_id')->nullable();
            }
        });

        foreach (['subjects', 'question_banks', 'candidates', 'exams', 'results', 'exam_audit_logs', 'proctoring_events'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'owner_type')) {
                    $table->string('owner_type')->nullable()->index();
                }
                if (! Schema::hasColumn($tableName, 'owner_id')) {
                    $table->unsignedBigInteger('owner_id')->nullable()->index();
                }
                if (! Schema::hasColumn($tableName, 'secondary_school_id')) {
                    $table->foreignId('secondary_school_id')->nullable()->constrained()->nullOnDelete();
                }
                if (! Schema::hasColumn($tableName, 'professional_school_id')) {
                    $table->foreignId('professional_school_id')->nullable()->constrained()->nullOnDelete();
                }
                if (! Schema::hasColumn($tableName, 'cbt_center_id')) {
                    $table->foreignId('cbt_center_id')->nullable()->constrained()->nullOnDelete();
                }
            });
        }

        Schema::table('exams', function (Blueprint $table): void {
            if (! Schema::hasColumn('exams', 'exam_owner_type')) {
                $table->string('exam_owner_type')->nullable()->index();
            }
            if (! Schema::hasColumn('exams', 'exam_owner_id')) {
                $table->unsignedBigInteger('exam_owner_id')->nullable()->index();
            }
            if (! Schema::hasColumn('exams', 'exam_category')) {
                $table->string('exam_category')->nullable()->index();
            }
            if (! Schema::hasColumn('exams', 'exam_mode')) {
                $table->string('exam_mode')->nullable()->index();
            }
        });

        Schema::table('question_banks', function (Blueprint $table): void {
            if (! Schema::hasColumn('question_banks', 'programme_id')) {
                $table->foreignId('programme_id')->nullable()->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('question_banks', 'course_id')) {
                $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('question_banks', 'module_id')) {
                $table->foreignId('module_id')->nullable()->constrained('modules')->nullOnDelete();
            }
        });

        Schema::table('candidates', function (Blueprint $table): void {
            if (! Schema::hasColumn('candidates', 'training_batch_id')) {
                $table->foreignId('training_batch_id')->nullable()->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('candidates', 'student_id')) {
                $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            }
        });

        foreach (['academic_sessions', 'academic_terms', 'school_classes', 'continuous_assessments', 'certificates', 'certificate_templates'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'secondary_school_id')) {
                    $table->foreignId('secondary_school_id')->nullable()->constrained()->nullOnDelete();
                }
                if (! Schema::hasColumn($tableName, 'professional_school_id')) {
                    $table->foreignId('professional_school_id')->nullable()->constrained()->nullOnDelete();
                }
                if (! Schema::hasColumn($tableName, 'cbt_center_id')) {
                    $table->foreignId('cbt_center_id')->nullable()->constrained()->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['certificate_templates', 'certificates', 'continuous_assessments', 'school_classes', 'academic_terms', 'academic_sessions'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                foreach (['cbt_center_id', 'professional_school_id', 'secondary_school_id'] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropConstrainedForeignId($column);
                    }
                }
            });
        }

        Schema::table('candidates', function (Blueprint $table): void {
            foreach (['student_id', 'training_batch_id'] as $column) {
                if (Schema::hasColumn('candidates', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
        });

        Schema::table('question_banks', function (Blueprint $table): void {
            foreach (['module_id', 'course_id', 'programme_id'] as $column) {
                if (Schema::hasColumn('question_banks', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
        });

        Schema::table('exams', function (Blueprint $table): void {
            foreach (['exam_mode', 'exam_category', 'exam_owner_id', 'exam_owner_type'] as $column) {
                if (Schema::hasColumn('exams', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        foreach (['proctoring_events', 'exam_audit_logs', 'results', 'exams', 'candidates', 'question_banks', 'subjects'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                foreach (['cbt_center_id', 'professional_school_id', 'secondary_school_id'] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropConstrainedForeignId($column);
                    }
                }
                foreach (['owner_id', 'owner_type'] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            foreach (['cbt_center_id', 'professional_school_id', 'secondary_school_id'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
            foreach (['active_context_id', 'active_context_type'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('organizations', function (Blueprint $table): void {
            foreach (['website', 'logo', 'description', 'organization_type'] as $column) {
                if (Schema::hasColumn('organizations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
