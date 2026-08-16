<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'institution_id')) {
                $table->foreignId('institution_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'faculty_id')) {
                $table->foreignId('faculty_id')->nullable()->after('institution_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'department_id')) {
                $table->foreignId('department_id')->nullable()->after('faculty_id')->constrained()->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach (['department_id', 'faculty_id', 'institution_id'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
        });
    }
};
