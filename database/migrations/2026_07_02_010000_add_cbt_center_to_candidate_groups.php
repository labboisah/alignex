<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('candidate_groups')) {
            return;
        }

        Schema::table('candidate_groups', function (Blueprint $table): void {
            if (! Schema::hasColumn('candidate_groups', 'cbt_center_id')) {
                $table->foreignId('cbt_center_id')->nullable()->after('organization_id')->constrained('cbt_centers')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('candidate_groups') || ! Schema::hasColumn('candidate_groups', 'cbt_center_id')) {
            return;
        }

        Schema::table('candidate_groups', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cbt_center_id');
        });
    }
};
