<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_answers', function (Blueprint $table): void {
            if (! Schema::hasColumn('candidate_answers', 'time_spent_seconds')) {
                $table->unsignedInteger('time_spent_seconds')->default(0)->after('is_flagged');
            }

            if (! Schema::hasColumn('candidate_answers', 'ip_address')) {
                $table->ipAddress('ip_address')->nullable()->after('time_spent_seconds');
            }

            if (! Schema::hasColumn('candidate_answers', 'device_fingerprint')) {
                $table->string('device_fingerprint')->nullable()->after('ip_address');
            }
        });
    }

    public function down(): void
    {
        Schema::table('candidate_answers', function (Blueprint $table): void {
            if (Schema::hasColumn('candidate_answers', 'device_fingerprint')) {
                $table->dropColumn('device_fingerprint');
            }

            if (Schema::hasColumn('candidate_answers', 'ip_address')) {
                $table->dropColumn('ip_address');
            }

            if (Schema::hasColumn('candidate_answers', 'time_spent_seconds')) {
                $table->dropColumn('time_spent_seconds');
            }
        });
    }
};
