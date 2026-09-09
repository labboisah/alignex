<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Adaptive areas may share a subject while selecting disjoint banks/modules.
        // Each paper row already has its own primary key.
        Schema::table('exam_subjects', function (Blueprint $table): void {
            $table->index(['exam_id', 'subject_id'], 'exam_subjects_exam_subject_idx');
        });
        Schema::table('exam_subjects', function (Blueprint $table): void {
            $table->dropUnique('exam_subjects_exam_subject_unique');
        });
    }

    public function down(): void
    {
        if (DB::table('exam_subjects')->whereNotNull('subject_id')
            ->select('exam_id', 'subject_id')->groupBy('exam_id', 'subject_id')
            ->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Cannot restore one-subject-per-exam uniqueness while adaptive sections share a subject. No sections were deleted.');
        }
        Schema::table('exam_subjects', function (Blueprint $table): void {
            $table->unique(['exam_id', 'subject_id'], 'exam_subjects_exam_subject_unique');
        });
        Schema::table('exam_subjects', function (Blueprint $table): void {
            $table->dropIndex('exam_subjects_exam_subject_idx');
        });
    }
};
