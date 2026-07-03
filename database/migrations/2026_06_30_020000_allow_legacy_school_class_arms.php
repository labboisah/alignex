<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_arms', function (Blueprint $table): void {
            if (! Schema::hasColumn('class_arms', 'school_id')) {
                $table->foreignId('school_id')->nullable()->after('id')->constrained()->nullOnDelete();
            }
        });

        if (DB::getDriverName() === 'mysql') {
            if (Schema::hasColumn('class_arms', 'secondary_school_id')) {
                DB::statement('ALTER TABLE class_arms MODIFY secondary_school_id BIGINT UNSIGNED NULL');
            }

            if (Schema::hasColumn('class_arms', 'school_id')) {
                DB::statement('ALTER TABLE class_arms MODIFY school_id BIGINT UNSIGNED NULL');
            }
        }
    }

    public function down(): void
    {
        Schema::table('class_arms', function (Blueprint $table): void {
            if (Schema::hasColumn('class_arms', 'school_id')) {
                $table->dropConstrainedForeignId('school_id');
            }
        });
    }
};
