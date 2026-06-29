<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_types', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->index('name');
        });

        Schema::create('subjects', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code');
            $table->text('description')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'code'], 'subjects_org_code_unique');
            $table->unique(['school_id', 'code'], 'subjects_school_code_unique');
            $table->index('name');
        });

        Schema::create('topics', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('parent_id')->nullable()->constrained('topics')->nullOnDelete();
            $table->string('name');
            $table->string('code');
            $table->text('description')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['subject_id', 'code'], 'topics_subject_code_unique');
        });

        Schema::create('question_banks', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('code');
            $table->text('description')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'code'], 'question_banks_org_code_unique');
            $table->unique(['school_id', 'code'], 'question_banks_school_code_unique');
            $table->index(['subject_id', 'status'], 'question_banks_subject_status_idx');
        });

        Schema::create('questions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('question_bank_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('topic_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('question_type')->index();
            $table->longText('stem');
            $table->text('explanation')->nullable();
            $table->string('difficulty')->default('medium')->index();
            $table->decimal('marks', 8, 2)->default(1);
            $table->decimal('negative_marks', 8, 2)->nullable();
            $table->string('status')->default('draft')->index();
            $table->json('scoring_metadata')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['question_bank_id', 'status'], 'questions_bank_status_idx');
            $table->index(['subject_id', 'topic_id', 'status'], 'questions_subject_topic_status_idx');
        });

        Schema::create('question_options', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('question_id')->constrained()->cascadeOnDelete();
            $table->string('label', 20);
            $table->text('option_text');
            $table->unsignedSmallInteger('display_order')->default(1);
            $table->boolean('is_correct')->default(false);
            $table->decimal('score_weight', 8, 2)->nullable();
            $table->timestamps();

            $table->unique(['question_id', 'label'], 'question_options_question_label_unique');
            $table->index(['question_id', 'display_order'], 'question_options_order_idx');
        });

        Schema::create('exams', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('exam_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('delivery_mode')->default('online')->index();
            $table->unsignedInteger('duration_minutes');
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->string('timezone')->default('UTC');
            $table->string('status')->default('draft')->index();
            $table->json('security_settings')->nullable();
            $table->json('navigation_settings')->nullable();
            $table->json('result_release_settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status'], 'exams_org_status_idx');
            $table->index(['school_id', 'status'], 'exams_school_status_idx');
            $table->index(['status', 'starts_at'], 'exams_status_starts_idx');
        });

        Schema::create('exam_sessions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('center_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->index();
            $table->unsignedInteger('capacity')->nullable();
            $table->string('status')->default('pending')->index();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['exam_id', 'status'], 'exam_sessions_exam_status_idx');
            $table->index(['center_id', 'starts_at'], 'exam_sessions_center_starts_idx');
        });

        Schema::create('exam_subjects', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('subject_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('display_order')->default(1);
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->decimal('total_marks', 8, 2)->default(0);
            $table->unsignedInteger('question_count')->default(0);
            $table->json('selection_rules')->nullable();
            $table->text('instructions')->nullable();
            $table->timestamps();

            $table->unique(['exam_id', 'subject_id'], 'exam_subjects_exam_subject_unique');
            $table->index(['exam_id', 'display_order'], 'exam_subjects_order_idx');
        });

        Schema::create('candidates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('candidate_number');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->json('metadata')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'candidate_number'], 'candidates_org_number_unique');
            $table->unique(['school_id', 'candidate_number'], 'candidates_school_number_unique');
        });

        Schema::create('candidate_exam_attempts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('exam_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('center_id')->nullable()->constrained()->nullOnDelete();
            $table->string('access_code_hash');
            $table->unsignedSmallInteger('attempt_number')->default(1);
            $table->string('status')->default('not_started')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('server_due_at')->nullable()->index();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamp('auto_submitted_at')->nullable();
            $table->timestamp('disqualified_at')->nullable();
            $table->text('disqualification_reason')->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->string('result_status')->nullable()->index();
            $table->string('device_fingerprint_hash')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['candidate_id', 'exam_id', 'attempt_number'], 'candidate_attempts_candidate_exam_unique');
            $table->index(['exam_id', 'status'], 'candidate_attempts_exam_status_idx');
            $table->index(['exam_session_id', 'status'], 'candidate_attempts_session_status_idx');
        });

        Schema::create('candidate_answers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('candidate_exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('question_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('scored_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('answer_payload')->nullable();
            $table->json('selected_option_ids')->nullable();
            $table->longText('answer_text')->nullable();
            $table->boolean('is_flagged')->default(false)->index();
            $table->timestamp('saved_at')->nullable()->index();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->decimal('score_awarded', 8, 2)->nullable();
            $table->timestamp('scored_at')->nullable();
            $table->timestamps();

            $table->unique(['candidate_exam_attempt_id', 'question_id'], 'candidate_answers_attempt_question_unique');
        });

        Schema::create('exam_audit_logs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('exam_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('exam_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('candidate_exam_attempt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_type')->default('system');
            $table->string('event_type')->index();
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['exam_id', 'occurred_at'], 'exam_audit_logs_exam_time_idx');
            $table->index(['candidate_exam_attempt_id', 'occurred_at'], 'exam_audit_logs_attempt_time_idx');
        });

        Schema::create('proctoring_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('exam_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('candidate_exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('center_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type')->index();
            $table->string('severity')->default('info')->index();
            $table->string('source')->default('candidate_app')->index();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('resolution_status')->nullable()->index();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['exam_session_id', 'occurred_at'], 'proctoring_events_session_time_idx');
            $table->index(['candidate_exam_attempt_id', 'occurred_at'], 'proctoring_events_attempt_time_idx');
            $table->index(['exam_id', 'severity', 'resolution_status'], 'proctoring_events_exam_severity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proctoring_events');
        Schema::dropIfExists('exam_audit_logs');
        Schema::dropIfExists('candidate_answers');
        Schema::dropIfExists('candidate_exam_attempts');
        Schema::dropIfExists('candidates');
        Schema::dropIfExists('exam_subjects');
        Schema::dropIfExists('exam_sessions');
        Schema::dropIfExists('exams');
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('question_banks');
        Schema::dropIfExists('topics');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('exam_types');
    }
};
