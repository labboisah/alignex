<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subject_teacher')) {
            return;
        }

        $needsColumn = ! Schema::hasColumn('subject_teacher', 'school_class_id');

        Schema::table('subject_teacher', function (Blueprint $table) use ($needsColumn): void {
            if ($needsColumn) {
                $table->foreignUlid('school_class_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained()
                    ->nullOnDelete();
            }
        });

        if ($needsColumn) {
            Schema::table('subject_teacher', function (Blueprint $table): void {
                $table->index(['school_class_id', 'subject_id'], 'subject_teacher_class_subject_index');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('subject_teacher') || ! Schema::hasColumn('subject_teacher', 'school_class_id')) {
            return;
        }

        Schema::table('subject_teacher', function (Blueprint $table): void {
            $table->dropIndex('subject_teacher_class_subject_index');
            $table->dropConstrainedForeignId('school_class_id');
        });
    }
};
