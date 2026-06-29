<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('exam_candidates')) {
            return;
        }

        Schema::create('exam_candidates', function (Blueprint $table): void {
            $table->foreignUlid('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('candidate_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('assigned')->index();
            $table->timestamps();

            $table->primary(['exam_id', 'candidate_id']);
            $table->index(['candidate_id', 'status'], 'exam_candidates_candidate_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_candidates');
    }
};
