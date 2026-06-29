<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_exam_attempts', function (Blueprint $table): void {
            if (! Schema::hasColumn('candidate_exam_attempts', 'result_hash')) {
                $table->string('result_hash')->nullable()->unique()->after('result_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('candidate_exam_attempts', function (Blueprint $table): void {
            if (Schema::hasColumn('candidate_exam_attempts', 'result_hash')) {
                $table->dropUnique('candidate_exam_attempts_result_hash_unique');
                $table->dropColumn('result_hash');
            }
        });
    }
};
