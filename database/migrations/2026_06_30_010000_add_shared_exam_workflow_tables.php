<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exam_participants')) {
            Schema::create('exam_participants', function (Blueprint $table): void {
                $table->id();
                $table->foreignUlid('exam_id')->constrained()->cascadeOnDelete();
                $table->string('participant_type');
                $table->string('participant_id');
                $table->string('status')->default('assigned')->index();
                $table->timestamps();

                $table->unique(['exam_id', 'participant_type', 'participant_id'], 'exam_participants_unique');
                $table->index(['participant_type', 'participant_id', 'status'], 'exam_participants_lookup');
            });
        }

        Schema::table('candidate_exam_attempts', function (Blueprint $table): void {
            if (Schema::hasColumn('candidate_exam_attempts', 'candidate_id')) {
                $table->ulid('candidate_id')->nullable()->change();
            }

            if (! Schema::hasColumn('candidate_exam_attempts', 'exam_participant_id')) {
                $table->foreignId('exam_participant_id')->nullable()->after('candidate_id')->constrained('exam_participants')->nullOnDelete();
            }

            if (! Schema::hasColumn('candidate_exam_attempts', 'participant_type')) {
                $table->string('participant_type')->nullable()->after('exam_participant_id')->index();
            }

            if (! Schema::hasColumn('candidate_exam_attempts', 'participant_id')) {
                $table->string('participant_id')->nullable()->after('participant_type')->index();
            }

            if (! Schema::hasColumn('candidate_exam_attempts', 'percentage')) {
                $table->decimal('percentage', 8, 2)->nullable()->after('total_marks');
            }

            if (! Schema::hasColumn('candidate_exam_attempts', 'grade')) {
                $table->string('grade')->nullable()->after('percentage');
            }

            if (! Schema::hasColumn('candidate_exam_attempts', 'duration_used_seconds')) {
                $table->unsignedInteger('duration_used_seconds')->nullable()->after('grade');
            }

            if (! Schema::hasColumn('candidate_exam_attempts', 'suspicious_event_count')) {
                $table->unsignedInteger('suspicious_event_count')->default(0)->after('duration_used_seconds');
            }

            if (! Schema::hasColumn('candidate_exam_attempts', 'certificate_eligible')) {
                $table->boolean('certificate_eligible')->default(false)->after('suspicious_event_count');
            }
        });

        Schema::table('candidate_papers', function (Blueprint $table): void {
            if (! Schema::hasColumn('candidate_papers', 'exam_participant_id')) {
                $table->foreignId('exam_participant_id')->nullable()->after('attempt_id')->constrained('exam_participants')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('candidate_papers', function (Blueprint $table): void {
            if (Schema::hasColumn('candidate_papers', 'exam_participant_id')) {
                $table->dropConstrainedForeignId('exam_participant_id');
            }
        });

        Schema::table('candidate_exam_attempts', function (Blueprint $table): void {
            foreach (['certificate_eligible', 'suspicious_event_count', 'duration_used_seconds', 'grade', 'percentage', 'participant_id', 'participant_type'] as $column) {
                if (Schema::hasColumn('candidate_exam_attempts', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('candidate_exam_attempts', 'exam_participant_id')) {
                $table->dropConstrainedForeignId('exam_participant_id');
            }
        });

        Schema::dropIfExists('exam_participants');
    }
};
