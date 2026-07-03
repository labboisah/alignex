<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            if (! Schema::hasColumn('subjects', 'school_class_id')) {
                $table->foreignUlid('school_class_id')->nullable()->after('secondary_school_id')->constrained()->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            if (Schema::hasColumn('subjects', 'school_class_id')) {
                $table->dropConstrainedForeignId('school_class_id');
            }
        });
    }
};
