<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_sessions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'code'], 'academic_sessions_school_code_unique');
        });

        Schema::create('academic_terms', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('academic_session_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['academic_session_id', 'code'], 'academic_terms_session_code_unique');
        });

        Schema::create('school_classes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code');
            $table->unsignedSmallInteger('level_order')->default(1);
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'code'], 'school_classes_school_code_unique');
        });

        Schema::create('student_groups', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('school_class_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_class_id', 'code'], 'student_groups_class_code_unique');
        });

        Schema::create('student_group_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('student_group_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('candidate_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['student_group_id', 'candidate_id'], 'student_group_candidate_unique');
        });

        Schema::create('continuous_assessments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('academic_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('academic_term_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('school_class_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('student_group_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('ca_score', 8, 2)->default(0);
            $table->decimal('exam_score', 8, 2)->default(0);
            $table->decimal('total_score', 8, 2)->default(0);
            $table->string('grade')->nullable();
            $table->text('teacher_comment')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['exam_id', 'candidate_id', 'subject_id'], 'continuous_assessments_result_unique');
            $table->index(['academic_term_id', 'school_class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('continuous_assessments');
        Schema::dropIfExists('student_group_members');
        Schema::dropIfExists('student_groups');
        Schema::dropIfExists('school_classes');
        Schema::dropIfExists('academic_terms');
        Schema::dropIfExists('academic_sessions');
    }
};
