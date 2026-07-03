<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('candidate_groups')) {
            Schema::create('candidate_groups', function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->string('code')->nullable()->unique();
                $table->text('description')->nullable();
                $table->string('status')->default('active')->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('candidate_group_candidate')) {
            Schema::create('candidate_group_candidate', function (Blueprint $table): void {
                $table->foreignUlid('candidate_group_id')->constrained('candidate_groups')->cascadeOnDelete();
                $table->foreignUlid('candidate_id')->constrained('candidates')->cascadeOnDelete();
                $table->timestamps();

                $table->primary(['candidate_group_id', 'candidate_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_group_candidate');
        Schema::dropIfExists('candidate_groups');
    }
};
