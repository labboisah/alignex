<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_readiness_packages', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 100);
            $table->string('version', 80);
            $table->unsignedInteger('capacity_profile');
            $table->decimal('backup_percent', 5, 2)->default(15.00);
            $table->unsignedInteger('autoboot_target_clients');
            $table->unsignedInteger('question_count');
            $table->unsignedInteger('subject_count')->default(0);
            $table->string('status', 20)->default('draft');
            $table->char('checksum_sha256', 64);
            $table->json('payload');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['code', 'version']);
            $table->index(['capacity_profile', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_readiness_packages');
    }
};
