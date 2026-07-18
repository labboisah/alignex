<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_activation_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cbt_center_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label')->nullable();
            $table->string('code_hash');
            $table->string('status')->default('active')->index();
            $table->unsignedInteger('max_activations')->default(1);
            $table->unsignedInteger('activation_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('license_expires_at')->nullable();
            $table->timestamp('last_activated_at')->nullable();
            $table->string('last_device_id')->nullable();
            $table->string('last_admin_email')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['cbt_center_id', 'status']);
        });

        Schema::create('offline_server_activations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('offline_activation_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cbt_center_id')->nullable()->constrained()->nullOnDelete();
            $table->string('device_id')->index();
            $table->string('admin_email');
            $table->string('center_name')->nullable();
            $table->string('license_key')->unique();
            $table->string('status')->default('activated')->index();
            $table->timestamp('activated_at');
            $table->timestamp('expires_at');
            $table->json('request_payload')->nullable();
            $table->timestamps();

            $table->unique(['offline_activation_code_id', 'device_id'], 'offline_activation_device_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_server_activations');
        Schema::dropIfExists('offline_activation_codes');
    }
};
