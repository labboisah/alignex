<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_exam_attempts', function (Blueprint $table): void {
            if (! Schema::hasColumn('candidate_exam_attempts', 'payment_status')) {
                $table->string('payment_status')->default('pending')->after('access_code');
            }

            if (! Schema::hasColumn('candidate_exam_attempts', 'payment_reference')) {
                $table->string('payment_reference')->nullable()->after('payment_status');
            }
        });

        Schema::create('certificate_templates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('exam_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('title')->default('Certificate of Achievement');
            $table->text('body');
            $table->string('signatory_name')->nullable();
            $table->string('signatory_title')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['exam_id', 'is_active']);
        });

        Schema::create('certificates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('candidate_exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('certificate_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('serial_number')->unique();
            $table->string('verification_hash')->unique();
            $table->string('status')->default('issued')->index();
            $table->timestamp('issued_at')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['candidate_exam_attempt_id', 'status'], 'certificates_attempt_status_unique');
            $table->index(['exam_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('certificate_templates');

        Schema::table('candidate_exam_attempts', function (Blueprint $table): void {
            if (Schema::hasColumn('candidate_exam_attempts', 'payment_reference')) {
                $table->dropColumn('payment_reference');
            }

            if (Schema::hasColumn('candidate_exam_attempts', 'payment_status')) {
                $table->dropColumn('payment_status');
            }
        });
    }
};
