<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adaptive_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('exam_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('owner_key');
            $table->string('engine_version')->default('simple-v1');
            $table->string('scoring_version')->default('recovery-v1');
            $table->json('settings');
            $table->json('blueprint');
            $table->json('readiness');
            $table->char('fingerprint', 64);
            $table->boolean('ready')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['exam_id', 'version']);
        });
        Schema::create('adaptive_pool_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('snapshot_id')->constrained('adaptive_snapshots')->restrictOnDelete();
            $table->foreignUlid('question_id')->constrained('questions')->restrictOnDelete();
            $table->string('area_key', 80);
            $table->string('difficulty', 10);
            $table->char('content_hash', 64);
            $table->longText('content');
            $table->timestamps();
            $table->unique(['snapshot_id', 'question_id']);
            $table->index(['snapshot_id', 'area_key', 'difficulty']);
        });
        Schema::create('adaptive_progressions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('snapshot_id')->constrained('adaptive_snapshots')->restrictOnDelete();
            $table->foreignUlid('exam_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('candidate_id')->constrained()->restrictOnDelete();
            $table->string('owner_key');
            $table->enum('status', ['prepared', 'active', 'blocked', 'completed', 'closed'])->default('prepared');
            $table->unsignedBigInteger('original_units');
            $table->unsignedBigInteger('earned_units')->default(0);
            $table->unsignedBigInteger('penalty_units')->default(0);
            $table->unsignedBigInteger('recoverable_units');
            $table->unsignedBigInteger('closed_units')->default(0);
            $table->unsignedInteger('state_version')->default(0);
            $table->timestamp('closes_at')->nullable();
            $table->string('stop_reason')->nullable();
            $table->timestamps();
            $table->unique(['exam_id', 'candidate_id']);
        });
        Schema::create('adaptive_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('progression_id')->constrained('adaptive_progressions')->restrictOnDelete();
            $table->foreignUlid('attempt_id')->unique()->constrained('candidate_exam_attempts')->restrictOnDelete();
            $table->unsignedSmallInteger('number');
            $table->enum('status', ['prepared', 'active', 'submitted', 'closed'])->default('prepared');
            $table->unsignedBigInteger('incoming_units');
            $table->unsignedSmallInteger('penalty_basis_points')->default(0);
            $table->unsignedBigInteger('penalty_units')->default(0);
            $table->unsignedBigInteger('available_units');
            $table->unsignedBigInteger('earned_units')->default(0);
            $table->json('weakness_snapshot');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['progression_id', 'number']);
        });
        Schema::create('adaptive_area_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('progression_id')->constrained('adaptive_progressions')->restrictOnDelete();
            $table->string('area_key', 80);
            $table->unsignedBigInteger('original_units');
            $table->unsignedBigInteger('earned_units')->default(0);
            $table->unsignedBigInteger('penalty_units')->default(0);
            $table->unsignedBigInteger('recoverable_units');
            $table->unsignedBigInteger('closed_units')->default(0);
            $table->unsignedInteger('evidence_count')->default(0);
            $table->enum('mastery', ['untested', 'insufficient_evidence', 'weak', 'mastered'])->default('untested');
            $table->timestamps();
            $table->unique(['progression_id', 'area_key']);
        });
        Schema::create('adaptive_attempt_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('attempt_id')->unique()->constrained('candidate_exam_attempts')->restrictOnDelete();
            $table->foreignId('snapshot_id')->constrained('adaptive_snapshots')->restrictOnDelete();
            $table->enum('delivery_mode', ['adaptive'])->default('adaptive');
            $table->unsignedInteger('state_version')->default(0);
            $table->unsignedInteger('step')->default(0);
            $table->string('stop_reason')->nullable();
            $table->timestamps();
        });
        Schema::create('adaptive_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('progression_id')->constrained('adaptive_progressions')->restrictOnDelete();
            $table->foreignId('level_id')->constrained('adaptive_levels')->restrictOnDelete();
            $table->foreignId('pool_item_id')->constrained('adaptive_pool_items')->restrictOnDelete();
            $table->foreignUlid('question_id')->constrained('questions')->restrictOnDelete();
            $table->unsignedInteger('step');
            $table->string('idempotency_key', 64);
            $table->json('decision');
            $table->timestamps();
            $table->unique(['level_id', 'step']);
            $table->unique(['level_id', 'idempotency_key']);
            $table->unique(['progression_id', 'question_id']);
        });
        Schema::create('adaptive_mark_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('progression_id')->constrained('adaptive_progressions')->restrictOnDelete();
            $table->foreignId('level_id')->nullable()->constrained('adaptive_levels')->restrictOnDelete();
            $table->string('area_key', 80)->nullable();
            $table->enum('kind', ['opening', 'penalty', 'earned', 'closed', 'adjustment']);
            $table->bigInteger('units');
            $table->string('idempotency_key', 64);
            $table->json('metadata');
            $table->timestamps();
            $table->unique(['progression_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        foreach (['adaptive_mark_entries', 'adaptive_decisions', 'adaptive_attempt_states', 'adaptive_area_balances', 'adaptive_levels', 'adaptive_progressions', 'adaptive_pool_items', 'adaptive_snapshots'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
