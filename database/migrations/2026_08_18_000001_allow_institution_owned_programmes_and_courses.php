<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programmes', function (Blueprint $table): void {
            if (Schema::hasColumn('programmes', 'professional_school_id')) {
                $table->foreignId('professional_school_id')->nullable()->change();
            }
        });

        Schema::table('courses', function (Blueprint $table): void {
            if (Schema::hasColumn('courses', 'professional_school_id')) {
                $table->foreignId('professional_school_id')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('programmes', function (Blueprint $table): void {
            if (Schema::hasColumn('programmes', 'professional_school_id')) {
                $table->foreignId('professional_school_id')->nullable(false)->change();
            }
        });

        Schema::table('courses', function (Blueprint $table): void {
            if (Schema::hasColumn('courses', 'professional_school_id')) {
                $table->foreignId('professional_school_id')->nullable(false)->change();
            }
        });
    }
};
