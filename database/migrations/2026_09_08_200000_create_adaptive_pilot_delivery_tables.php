<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adaptive_pilot_controls', function (Blueprint $t): void {
            $t->id();
            $t->foreignUlid('exam_id')->unique()->constrained()->restrictOnDelete();
            $t->string('owner_key');
            $t->boolean('online_enabled')->default(false);
            $t->boolean('offline_enabled')->default(false);
            $t->text('purpose');
            $t->foreignId('updated_by')->constrained('users');
            $t->timestamps();
        });
        Schema::create('adaptive_offline_packages', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUlid('exam_id')->constrained()->restrictOnDelete();
            $t->foreignId('snapshot_id')->constrained('adaptive_snapshots')->restrictOnDelete();
            $t->foreignId('activation_id')->constrained('offline_server_activations')->restrictOnDelete();
            $t->string('owner_key');
            $t->longText('payload');
            $t->char('payload_hash', 64);
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });
        Schema::create('adaptive_offline_leases', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('package_id')->constrained('adaptive_offline_packages')->restrictOnDelete();
            $t->foreignUlid('exam_id')->constrained()->restrictOnDelete();
            $t->foreignUlid('candidate_id')->constrained()->restrictOnDelete();
            $t->string('status')->default('reserved');
            $t->char('transcript_hash', 64)->nullable();
            $t->longText('transcript')->nullable();
            $t->json('result')->nullable();
            $t->string('error_code')->nullable();
            $t->timestamps();
            $t->unique(['exam_id', 'candidate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adaptive_offline_leases');
        Schema::dropIfExists('adaptive_offline_packages');
        Schema::dropIfExists('adaptive_pilot_controls');
    }
};
