<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table): void {
            if (! Schema::hasColumn('candidates', 'cbt_center_id')) {
                $table->foreignId('cbt_center_id')->nullable()->after('course_id')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('candidates', 'nin')) {
                $table->string('nin')->nullable()->after('phone');
            }

            if (! Schema::hasColumn('candidates', 'photo')) {
                $table->string('photo')->nullable()->after('nin');
            }
        });

        Schema::table('exams', function (Blueprint $table): void {
            if (! Schema::hasColumn('exams', 'question_bank_id')) {
                $table->foreignUlid('question_bank_id')->nullable()->after('subject_id')->constrained('question_banks')->nullOnDelete();
            }
        });

        Schema::table('questions', function (Blueprint $table): void {
            if (! Schema::hasColumn('questions', 'tags')) {
                $table->json('tags')->nullable()->after('scoring_metadata');
            }
        });

        if (! Schema::hasTable('exam_center_assignments')) {
            Schema::create('exam_center_assignments', function (Blueprint $table): void {
                $table->id();
                $table->foreignUlid('exam_id')->constrained()->cascadeOnDelete();
                $table->foreignId('cbt_center_id')->constrained()->cascadeOnDelete();
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status')->default('assigned')->index();
                $table->timestamp('assigned_at')->nullable();
                $table->timestamps();

                $table->unique(['exam_id', 'cbt_center_id'], 'exam_center_assignments_exam_center_unique');
                $table->index(['cbt_center_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_center_assignments');

        Schema::table('questions', function (Blueprint $table): void {
            if (Schema::hasColumn('questions', 'tags')) {
                $table->dropColumn('tags');
            }
        });

        Schema::table('exams', function (Blueprint $table): void {
            if (Schema::hasColumn('exams', 'question_bank_id')) {
                $table->dropConstrainedForeignId('question_bank_id');
            }
        });

        Schema::table('candidates', function (Blueprint $table): void {
            foreach (['photo', 'nin'] as $column) {
                if (Schema::hasColumn('candidates', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
