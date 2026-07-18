<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_facilitator', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('professional_school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->nullable()->constrained('modules')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'course_id', 'module_id'], 'course_facilitator_user_course_module_unique');
            $table->index(['professional_school_id', 'course_id'], 'course_facilitator_school_course_index');
            $table->index(['professional_school_id', 'module_id'], 'course_facilitator_school_module_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_facilitator');
    }
};
