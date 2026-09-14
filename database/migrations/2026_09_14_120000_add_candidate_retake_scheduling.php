<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_exam_attempts', function (Blueprint $table): void {
            $table->foreignUlid('retake_of_attempt_id')->nullable()->constrained('candidate_exam_attempts')->restrictOnDelete();
            $table->timestamp('retake_starts_at')->nullable();
            $table->timestamp('retake_ends_at')->nullable()->index();
            $table->unsignedSmallInteger('retake_duration_minutes')->nullable();
            $table->text('retake_reason')->nullable();
            $table->foreignId('retake_scheduled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('retake_cancelled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('candidate_exam_attempts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('retake_of_attempt_id');
            $table->dropConstrainedForeignId('retake_scheduled_by');
            $table->dropColumn(['retake_starts_at', 'retake_ends_at', 'retake_duration_minutes', 'retake_reason', 'retake_cancelled_at']);
        });
    }
};
