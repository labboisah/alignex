<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_exam_attempts', function (Blueprint $table): void {
            if (! Schema::hasColumn('candidate_exam_attempts', 'total_questions')) {
                $table->unsignedInteger('total_questions')->default(0)->after('score');
            }

            if (! Schema::hasColumn('candidate_exam_attempts', 'total_marks')) {
                $table->decimal('total_marks', 8, 2)->default(0)->after('total_questions');
            }

            if (! Schema::hasColumn('candidate_exam_attempts', 'device_fingerprint')) {
                $table->string('device_fingerprint')->nullable()->after('ip_address');
            }
        });

        if (! Schema::hasTable('candidate_papers')) {
            Schema::create('candidate_papers', function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->foreignUlid('attempt_id')->constrained('candidate_exam_attempts')->cascadeOnDelete();
                $table->foreignUlid('question_id')->constrained()->restrictOnDelete();
                $table->unsignedInteger('question_order');
                $table->json('option_order')->nullable();
                $table->timestamps();

                $table->unique(['attempt_id', 'question_id'], 'candidate_papers_attempt_question_unique');
                $table->unique(['attempt_id', 'question_order'], 'candidate_papers_attempt_order_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_papers');

        Schema::table('candidate_exam_attempts', function (Blueprint $table): void {
            if (Schema::hasColumn('candidate_exam_attempts', 'device_fingerprint')) {
                $table->dropColumn('device_fingerprint');
            }

            if (Schema::hasColumn('candidate_exam_attempts', 'total_marks')) {
                $table->dropColumn('total_marks');
            }

            if (Schema::hasColumn('candidate_exam_attempts', 'total_questions')) {
                $table->dropColumn('total_questions');
            }
        });
    }
};
