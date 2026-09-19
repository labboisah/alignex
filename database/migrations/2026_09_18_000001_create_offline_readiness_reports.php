<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_readiness_reports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('drill_id', 100)->unique();
            $table->foreignId('activation_id')->constrained('offline_server_activations');
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cbt_center_id')->nullable()->constrained()->nullOnDelete();
            $table->string('report_version', 80);
            $table->string('drill_name');
            $table->string('readiness_status', 30);
            $table->string('content_pack_version', 100);
            $table->unsignedInteger('expected_clients')->default(0);
            $table->unsignedInteger('connected_clients')->default(0);
            $table->unsignedInteger('completed_clients')->default(0);
            $table->unsignedInteger('failed_clients')->default(0);
            $table->unsignedInteger('total_questions')->default(0);
            $table->unsignedInteger('total_answers')->default(0);
            $table->unsignedInteger('total_submissions')->default(0);
            $table->unsignedInteger('event_count')->default(0);
            $table->char('payload_hash', 64);
            $table->json('payload');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->index(['cbt_center_id', 'readiness_status']);
            $table->index(['organization_id', 'readiness_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_readiness_reports');
    }
};
