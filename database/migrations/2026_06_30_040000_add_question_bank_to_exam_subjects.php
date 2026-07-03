<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_subjects', function (Blueprint $table): void {
            if (! Schema::hasColumn('exam_subjects', 'question_bank_id')) {
                $table->foreignUlid('question_bank_id')->nullable()->after('subject_id')->constrained('question_banks')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('exam_subjects', function (Blueprint $table): void {
            if (Schema::hasColumn('exam_subjects', 'question_bank_id')) {
                $table->dropConstrainedForeignId('question_bank_id');
            }
        });
    }
};
