<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offline_readiness_packages', function (Blueprint $table): void {
            $table->json('paper_rows')->nullable()->after('question_bank_ids');
        });
    }

    public function down(): void
    {
        Schema::table('offline_readiness_packages', function (Blueprint $table): void {
            $table->dropColumn('paper_rows');
        });
    }
};
