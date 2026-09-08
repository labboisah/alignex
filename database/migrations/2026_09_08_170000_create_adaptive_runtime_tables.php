<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adaptive_level_runs', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('level_id')->unique()->constrained('adaptive_levels')->restrictOnDelete();
            $t->foreignId('progression_id')->constrained('adaptive_progressions')->restrictOnDelete();
            $t->string('start_key', 64);
            $t->boolean('is_practice')->default(false);
            $t->json('area_plan');
            $t->timestamps();
            $t->unique(['progression_id', 'start_key']);
        });
        Schema::create('adaptive_responses', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('decision_id')->unique()->constrained('adaptive_decisions')->restrictOnDelete();
            $t->foreignId('level_id')->constrained('adaptive_levels')->restrictOnDelete();
            $t->longText('selected_options');
            $t->boolean('is_correct')->nullable();
            $t->unsignedBigInteger('earned_units')->default(0);
            $t->string('commit_key', 64)->nullable();
            $t->timestamp('committed_at')->nullable();
            $t->timestamps();
            $t->unique(['level_id', 'commit_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adaptive_responses');
        Schema::dropIfExists('adaptive_level_runs');
    }
};
