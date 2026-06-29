<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_exam_attempts', function (Blueprint $table): void {
            if (! Schema::hasColumn('candidate_exam_attempts', 'access_code')) {
                $table->string('access_code')->nullable()->after('access_code_hash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('candidate_exam_attempts', function (Blueprint $table): void {
            if (Schema::hasColumn('candidate_exam_attempts', 'access_code')) {
                $table->dropColumn('access_code');
            }
        });
    }
};
