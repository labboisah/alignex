<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->unique();
            $table->string('name');
            $table->json('channels');
            $table->string('email_subject')->nullable();
            $table->text('email_body')->nullable();
            $table->text('sms_body')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->index();
            $table->string('channel')->index();
            $table->string('status')->default('pending')->index();
            $table->string('provider')->nullable()->index();
            $table->string('provider_message_id')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_email')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->string('subject')->nullable();
            $table->text('body');
            $table->json('payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_templates');
    }
};
