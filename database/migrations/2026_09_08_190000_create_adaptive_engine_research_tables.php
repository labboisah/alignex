<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adaptive_calibrations', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('snapshot_id')->constrained('adaptive_snapshots')->restrictOnDelete();
            $t->string('owner_key');
            $t->unsignedInteger('version');
            $t->enum('status', ['draft', 'reviewed', 'revoked'])->default('draft');
            $t->char('fingerprint', 64);
            $t->longText('payload');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
            $t->unique(['snapshot_id', 'version']);
        });
        Schema::create('adaptive_engine_runs', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('calibration_id')->constrained('adaptive_calibrations')->restrictOnDelete();
            $t->foreignId('progression_id')->constrained('adaptive_progressions')->restrictOnDelete();
            $t->foreignId('level_id')->constrained('adaptive_levels')->restrictOnDelete();
            $t->foreignId('replay_of')->nullable()->constrained('adaptive_engine_runs')->restrictOnDelete();
            $t->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $t->enum('status', ['succeeded', 'failed']);
            $t->string('error_code')->nullable();
            $t->char('request_hash', 64);
            $t->longText('request_payload');
            $t->longText('result')->nullable();
            $t->unsignedInteger('duration_ms');
            $t->timestamps();
            $t->index(['progression_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adaptive_engine_runs');
        Schema::dropIfExists('adaptive_calibrations');
    }
};
