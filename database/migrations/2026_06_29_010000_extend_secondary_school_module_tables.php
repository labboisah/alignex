<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('academic_sessions', 'is_active')) {
                $table->boolean('is_active')->default(false)->after('status')->index();
            }
        });

        Schema::table('academic_terms', function (Blueprint $table): void {
            if (! Schema::hasColumn('academic_terms', 'is_active')) {
                $table->boolean('is_active')->default(false)->after('status')->index();
            }
        });

        Schema::table('school_classes', function (Blueprint $table): void {
            if (! Schema::hasColumn('school_classes', 'level')) {
                $table->string('level')->nullable()->after('code');
            }
        });

        Schema::table('class_arms', function (Blueprint $table): void {
            if (! Schema::hasColumn('class_arms', 'class_teacher_id')) {
                $table->foreignId('class_teacher_id')->nullable()->after('code')->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('students', function (Blueprint $table): void {
            if (! Schema::hasColumn('students', 'gender')) {
                $table->string('gender')->nullable()->after('last_name');
            }
            if (! Schema::hasColumn('students', 'guardian_name')) {
                $table->string('guardian_name')->nullable()->after('phone');
            }
            if (! Schema::hasColumn('students', 'guardian_phone')) {
                $table->string('guardian_phone')->nullable()->after('guardian_name');
            }
            if (! Schema::hasColumn('students', 'photo')) {
                $table->string('photo')->nullable()->after('guardian_phone');
            }
        });

        Schema::table('exams', function (Blueprint $table): void {
            if (! Schema::hasColumn('exams', 'academic_session_id')) {
                $table->foreignUlid('academic_session_id')->nullable()->after('exam_owner_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('exams', 'academic_term_id')) {
                $table->foreignUlid('academic_term_id')->nullable()->after('academic_session_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('exams', 'school_class_id')) {
                $table->foreignUlid('school_class_id')->nullable()->after('academic_term_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('exams', 'class_arm_id')) {
                $table->foreignId('class_arm_id')->nullable()->after('school_class_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('exams', 'subject_id')) {
                $table->foreignUlid('subject_id')->nullable()->after('class_arm_id')->constrained()->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table): void {
            foreach (['subject_id', 'class_arm_id', 'school_class_id', 'academic_term_id', 'academic_session_id'] as $column) {
                if (Schema::hasColumn('exams', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
        });

        Schema::table('students', function (Blueprint $table): void {
            foreach (['photo', 'guardian_phone', 'guardian_name', 'gender'] as $column) {
                if (Schema::hasColumn('students', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('class_arms', function (Blueprint $table): void {
            if (Schema::hasColumn('class_arms', 'class_teacher_id')) {
                $table->dropConstrainedForeignId('class_teacher_id');
            }
        });

        Schema::table('school_classes', function (Blueprint $table): void {
            if (Schema::hasColumn('school_classes', 'level')) {
                $table->dropColumn('level');
            }
        });

        Schema::table('academic_terms', function (Blueprint $table): void {
            if (Schema::hasColumn('academic_terms', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });

        Schema::table('academic_sessions', function (Blueprint $table): void {
            if (Schema::hasColumn('academic_sessions', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};
