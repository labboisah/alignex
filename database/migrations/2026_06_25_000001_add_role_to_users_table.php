<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default('super_admin')->after('email');
            $table->foreignId('organization_id')->nullable()->after('role')->constrained()->nullOnDelete();
            $table->foreignId('center_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
            $table->foreignId('school_id')->nullable()->after('center_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['center_id']);
            $table->dropForeign(['school_id']);
            $table->dropForeign(['organization_id']);
            $table->dropColumn(['role', 'organization_id', 'center_id', 'school_id']);
        });
    }
};
