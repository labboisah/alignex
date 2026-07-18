<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offline_activation_codes', function (Blueprint $table): void {
            $table->foreignId('created_by_user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->text('code_encrypted')->nullable()->after('code_hash');
        });
    }

    public function down(): void
    {
        Schema::table('offline_activation_codes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropColumn('code_encrypted');
        });
    }
};
