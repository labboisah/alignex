<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_releases')) {
            return;
        }

        $this->activateRelease(
            'server',
            '0.1.3',
            'downloads/offline-server/AlignEx-Center-Server-0.1.3-win-unpacked.zip'
        );

        $this->activateRelease(
            'client_app',
            '0.1.3',
            'downloads/candidate-client/AlignEx-Client-App-Setup-0.1.3.exe'
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('app_releases')) {
            return;
        }

        DB::table('app_releases')
            ->whereIn('artifact', ['server', 'client_app'])
            ->where('version', '0.1.3')
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }

    private function activateRelease(string $artifact, string $version, string $filePath): void
    {
        $absolutePath = public_path($filePath);

        if (! is_file($absolutePath) || filesize($absolutePath) <= 0) {
            return;
        }

        DB::table('app_releases')
            ->where('artifact', $artifact)
            ->where('version', '!=', $version)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

        DB::table('app_releases')->updateOrInsert(
            [
                'artifact' => $artifact,
                'version' => $version,
            ],
            [
                'filename' => basename($absolutePath),
                'file_path' => $filePath,
                'size_bytes' => filesize($absolutePath) ?: 0,
                'sha256' => hash_file('sha256', $absolutePath),
                'release_notes' => 'AlignEx offline server/client 0.1.3 release artifacts.',
                'is_active' => true,
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
};
