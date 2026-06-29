<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_registration_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('entity_type')->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->string('admin_name');
            $table->string('admin_email')->unique();
            $table->string('password');
            $table->string('entity_name');
            $table->string('entity_code');
            $table->string('location')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->string('contact_person');
            $table->string('phone')->nullable();
            $table->string('entity_email')->unique();
            $table->text('address')->nullable();
            $table->string('legal_registration_number')->nullable();
            $table->string('website')->nullable();
            $table->unsignedSmallInteger('years_in_operation')->nullable();
            $table->string('operating_scope')->nullable();
            $table->string('accreditation_body')->nullable();
            $table->string('accreditation_number')->nullable();
            $table->text('facility_summary')->nullable();
            $table->text('exam_experience')->nullable();
            $table->unsignedInteger('expected_candidates')->nullable();
            $table->string('status')->default('pending')->index();
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['entity_type', 'entity_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_registration_requests');
    }
};
