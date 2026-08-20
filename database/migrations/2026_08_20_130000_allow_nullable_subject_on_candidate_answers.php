<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_answers', function (Blueprint $table): void {
            if (Schema::hasColumn('candidate_answers', 'subject_id')) {
                $table->foreignUlid('subject_id')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        //
    }
};
