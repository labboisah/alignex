<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_performance_profiles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('topic_id')->nullable()->constrained()->nullOnDelete();
            $table->string('difficulty')->nullable()->index();
            $table->unsignedInteger('total_questions')->default(0);
            $table->unsignedInteger('correct_answers')->default(0);
            $table->decimal('score_percentage', 5, 2)->default(0);
            $table->string('mastery_level')->index();
            $table->timestamps();

            $table->index(['candidate_id', 'exam_id'], 'candidate_performance_candidate_exam_idx');
            $table->index(['exam_id', 'subject_id', 'topic_id'], 'candidate_performance_exam_topic_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_performance_profiles');
    }
};
