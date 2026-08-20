<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('exam_supervisors')) {
            return;
        }

        Schema::create('exam_supervisors', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role')->default('supervisor');
            $table->json('permissions')->nullable();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['exam_id', 'user_id'], 'exam_supervisors_exam_user_unique');
            $table->index(['user_id', 'revoked_at'], 'exam_supervisors_user_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_supervisors');
    }
};
