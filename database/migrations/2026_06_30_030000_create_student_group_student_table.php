<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_group_student')) {
            Schema::create('student_group_student', function (Blueprint $table): void {
                $table->id();
                $table->foreignUlid('student_group_id')->constrained()->cascadeOnDelete();
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['student_group_id', 'student_id'], 'student_group_student_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_group_student');
    }
};
