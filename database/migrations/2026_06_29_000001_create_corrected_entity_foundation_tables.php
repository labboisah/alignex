<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('secondary_schools')) {
            Schema::create('secondary_schools', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->string('code')->unique();
                $table->string('contact_person');
                $table->string('email')->unique();
                $table->string('phone')->nullable();
                $table->text('address')->nullable();
                $table->string('status')->default('active')->index();
                $table->timestamps();

                $table->index(['organization_id', 'status']);
                $table->index('name');
            });
        }

        if (! Schema::hasTable('professional_schools')) {
            Schema::create('professional_schools', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->string('code')->unique();
                $table->string('contact_person');
                $table->string('email')->unique();
                $table->string('phone')->nullable();
                $table->text('address')->nullable();
                $table->string('status')->default('active')->index();
                $table->timestamps();

                $table->index(['organization_id', 'status']);
                $table->index('name');
            });
        }

        if (! Schema::hasTable('cbt_centers')) {
            Schema::create('cbt_centers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->string('code')->unique();
                $table->string('location');
                $table->unsignedInteger('capacity')->default(0);
                $table->string('contact_person');
                $table->string('email')->unique();
                $table->string('phone')->nullable();
                $table->string('status')->default('active')->index();
                $table->timestamps();

                $table->index(['organization_id', 'status']);
                $table->index('name');
            });
        }

        if (! Schema::hasTable('class_arms')) {
            Schema::create('class_arms', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('secondary_school_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignUlid('school_class_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('code');
                $table->string('status')->default('active')->index();
                $table->timestamps();

                $table->unique(['secondary_school_id', 'code'], 'class_arms_secondary_school_code_unique');
                $table->index(['secondary_school_id', 'status']);
            });
        }

        if (! Schema::hasTable('students')) {
            Schema::create('students', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('secondary_school_id')->constrained()->cascadeOnDelete();
                $table->foreignUlid('school_class_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('class_arm_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignUlid('candidate_id')->nullable()->constrained()->nullOnDelete();
                $table->string('admission_number');
                $table->string('first_name');
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->date('date_of_birth')->nullable();
                $table->json('metadata')->nullable();
                $table->string('status')->default('active')->index();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['secondary_school_id', 'admission_number'], 'students_secondary_school_admission_unique');
                $table->index(['secondary_school_id', 'status']);
            });
        }

        if (! Schema::hasTable('report_cards')) {
            Schema::create('report_cards', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('secondary_school_id')->constrained()->cascadeOnDelete();
                $table->foreignUlid('academic_session_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignUlid('academic_term_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignUlid('candidate_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignUlid('exam_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('total_score', 8, 2)->default(0);
                $table->decimal('average_score', 5, 2)->default(0);
                $table->string('grade')->nullable();
                $table->string('status')->default('draft')->index();
                $table->json('metadata')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['secondary_school_id', 'status']);
            });
        }

        if (! Schema::hasTable('student_group_student')) {
            Schema::create('student_group_student', function (Blueprint $table): void {
                $table->id();
                $table->foreignUlid('student_group_id')->constrained()->cascadeOnDelete();
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['student_group_id', 'student_id'], 'student_group_student_unique');
            });
        }

        if (! Schema::hasTable('programmes')) {
            Schema::create('programmes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('professional_school_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('code');
                $table->text('description')->nullable();
                $table->string('status')->default('active')->index();
                $table->timestamps();

                $table->unique(['professional_school_id', 'code'], 'programmes_professional_school_code_unique');
                $table->index(['professional_school_id', 'status']);
            });
        }

        if (! Schema::hasTable('courses')) {
            Schema::create('courses', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('professional_school_id')->constrained()->cascadeOnDelete();
                $table->foreignId('programme_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('code');
                $table->text('description')->nullable();
                $table->string('status')->default('active')->index();
                $table->timestamps();

                $table->unique(['professional_school_id', 'code'], 'courses_professional_school_code_unique');
                $table->index(['professional_school_id', 'programme_id', 'status'], 'courses_prof_school_programme_status_idx');
            });
        }

        if (! Schema::hasTable('modules')) {
            Schema::create('modules', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('professional_school_id')->constrained()->cascadeOnDelete();
                $table->foreignId('programme_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('course_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('code');
                $table->text('description')->nullable();
                $table->string('status')->default('active')->index();
                $table->timestamps();

                $table->unique(['professional_school_id', 'code'], 'modules_professional_school_code_unique');
                $table->index(['professional_school_id', 'course_id', 'status'], 'modules_prof_school_course_status_idx');
            });
        }

        if (! Schema::hasTable('training_batches')) {
            Schema::create('training_batches', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('professional_school_id')->constrained()->cascadeOnDelete();
                $table->foreignId('programme_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->string('code');
                $table->date('starts_on')->nullable();
                $table->date('ends_on')->nullable();
                $table->string('status')->default('active')->index();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['professional_school_id', 'code'], 'training_batches_professional_school_code_unique');
                $table->index(['professional_school_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('training_batches');
        Schema::dropIfExists('modules');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('programmes');
        Schema::dropIfExists('student_group_student');
        Schema::dropIfExists('report_cards');
        Schema::dropIfExists('students');
        Schema::dropIfExists('class_arms');
        Schema::dropIfExists('cbt_centers');
        Schema::dropIfExists('professional_schools');
        Schema::dropIfExists('secondary_schools');
    }
};
