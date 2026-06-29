<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table): void {
            if (! Schema::hasColumn('certificate_templates', 'institution_name')) {
                $table->string('institution_name')->nullable()->after('title');
            }

            if (! Schema::hasColumn('certificate_templates', 'logo_url')) {
                $table->string('logo_url')->nullable()->after('institution_name');
            }

            if (! Schema::hasColumn('certificate_templates', 'primary_color')) {
                $table->string('primary_color', 20)->default('#0F7A3A')->after('logo_url');
            }

            if (! Schema::hasColumn('certificate_templates', 'accent_color')) {
                $table->string('accent_color', 20)->default('#F59E0B')->after('primary_color');
            }

            if (! Schema::hasColumn('certificate_templates', 'background_color')) {
                $table->string('background_color', 20)->default('#FFFFFF')->after('accent_color');
            }

            if (! Schema::hasColumn('certificate_templates', 'use_logo_watermark')) {
                $table->boolean('use_logo_watermark')->default(true)->after('background_color');
            }
        });
    }

    public function down(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table): void {
            foreach (['use_logo_watermark', 'background_color', 'accent_color', 'primary_color', 'logo_url', 'institution_name'] as $column) {
                if (Schema::hasColumn('certificate_templates', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
