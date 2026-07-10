<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_teacher', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('secondary_school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'school_class_id', 'subject_id'], 'subject_teacher_user_class_subject_unique');
            $table->index(['school_class_id', 'subject_id'], 'subject_teacher_class_subject_index');
            $table->index(['school_id', 'subject_id'], 'subject_teacher_legacy_school_subject_index');
            $table->index(['secondary_school_id', 'subject_id'], 'subject_teacher_secondary_school_subject_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_teacher');
    }
};
