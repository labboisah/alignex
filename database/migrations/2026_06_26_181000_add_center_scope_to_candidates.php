<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table): void {
            if (! Schema::hasColumn('candidates', 'center_id')) {
                $table->foreignId('center_id')->nullable()->after('school_id')->constrained()->nullOnDelete();
                $table->unique(['center_id', 'candidate_number'], 'candidates_center_number_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table): void {
            if (Schema::hasColumn('candidates', 'center_id')) {
                $table->dropUnique('candidates_center_number_unique');
                $table->dropConstrainedForeignId('center_id');
            }
        });
    }
};
