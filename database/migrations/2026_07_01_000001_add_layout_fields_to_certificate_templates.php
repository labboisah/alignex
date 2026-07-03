<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table): void {
            if (! Schema::hasColumn('certificate_templates', 'theme')) {
                $table->string('theme', 50)->default('classic')->after('background_color');
            }

            if (! Schema::hasColumn('certificate_templates', 'paper_size')) {
                $table->string('paper_size', 20)->default('a4')->after('theme');
            }

            if (! Schema::hasColumn('certificate_templates', 'orientation')) {
                $table->string('orientation', 20)->default('landscape')->after('paper_size');
            }

            if (! Schema::hasColumn('certificate_templates', 'template_key')) {
                $table->string('template_key', 50)->default('formal')->after('orientation');
            }
        });
    }

    public function down(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table): void {
            foreach (['template_key', 'orientation', 'paper_size', 'theme'] as $column) {
                if (Schema::hasColumn('certificate_templates', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
