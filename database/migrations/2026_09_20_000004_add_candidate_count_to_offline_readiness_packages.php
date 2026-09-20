<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offline_readiness_packages', function (Blueprint $table): void {
            $table->unsignedInteger('candidate_count')->default(0)->after('capacity_profile');
        });
    }

    public function down(): void
    {
        Schema::table('offline_readiness_packages', function (Blueprint $table): void {
            $table->dropColumn('candidate_count');
        });
    }
};
