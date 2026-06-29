<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table): void {
            $table->foreignId('center_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
            $table->string('mode')->default('traditional')->after('description')->index();
            $table->decimal('total_marks', 8, 2)->default(0)->after('duration_minutes');
            $table->decimal('pass_mark', 8, 2)->default(0)->after('total_marks');
            $table->json('settings')->nullable()->after('result_release_settings');

            $table->index(['center_id', 'status'], 'exams_center_status_idx');
        });

        Schema::table('exam_subjects', function (Blueprint $table): void {
            $table->decimal('marks_per_question', 8, 2)->default(1)->after('question_count');
            $table->json('difficulty_distribution')->nullable()->after('selection_rules');
        });
    }

    public function down(): void
    {
        Schema::table('exam_subjects', function (Blueprint $table): void {
            $table->dropColumn(['marks_per_question', 'difficulty_distribution']);
        });

        Schema::table('exams', function (Blueprint $table): void {
            $table->dropIndex('exams_center_status_idx');
            $table->dropConstrainedForeignId('center_id');
            $table->dropColumn(['mode', 'total_marks', 'pass_mark', 'settings']);
        });
    }
};
