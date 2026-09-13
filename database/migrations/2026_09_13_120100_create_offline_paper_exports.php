<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_paper_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('attempt_id')->constrained('candidate_exam_attempts');
            $table->foreignId('activation_id')->constrained('offline_server_activations');
            $table->string('package_id', 200);
            $table->timestamps();
            $table->unique(['attempt_id', 'activation_id', 'package_id'], 'offline_paper_export_identity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_paper_exports');
    }
};
