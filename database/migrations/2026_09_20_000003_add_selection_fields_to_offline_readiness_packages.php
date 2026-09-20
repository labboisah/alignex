<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offline_readiness_packages', function (Blueprint $table): void {
            $table->json('candidate_ids')->nullable()->after('payload');
            $table->json('subject_ids')->nullable()->after('candidate_ids');
            $table->json('question_bank_ids')->nullable()->after('subject_ids');
        });
    }

    public function down(): void
    {
        Schema::table('offline_readiness_packages', function (Blueprint $table): void {
            $table->dropColumn(['candidate_ids', 'subject_ids', 'question_bank_ids']);
        });
    }
};
