<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_papers', function (Blueprint $table): void {
            $table->decimal('marks', 8, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('candidate_papers', function (Blueprint $table): void {
            $table->dropColumn('marks');
        });
    }
};
