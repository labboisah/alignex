<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_result_receipts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('attempt_id')->unique()->constrained('candidate_exam_attempts');
            $table->foreignId('activation_id')->constrained('offline_server_activations');
            $table->string('upload_id', 100);
            $table->string('package_id', 200);
            $table->char('payload_hash', 64);
            $table->decimal('local_score', 12, 2)->nullable();
            $table->decimal('official_score', 12, 2)->nullable();
            $table->boolean('legacy_package')->default(false);
            $table->timestamps();
            $table->unique(['activation_id', 'upload_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_result_receipts');
    }
};
