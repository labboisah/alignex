<?php

use App\Support\AccessControl;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_releases', function (Blueprint $table): void {
            $table->id();
            $table->string('artifact', 40);
            $table->string('version', 40);
            $table->string('filename');
            $table->string('file_path');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('sha256', 64);
            $table->text('release_notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['artifact', 'is_active', 'published_at']);
            $table->unique(['artifact', 'version']);
        });

        $permission = AccessControl::permissions()['manageAppReleases'];
        DB::table('permissions')->updateOrInsert(
            ['name' => 'manageAppReleases'],
            [
                'label' => $permission['label'],
                'group' => $permission['group'],
                'description' => $permission['description'],
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $permissionId = DB::table('permissions')->where('name', 'manageAppReleases')->value('id');
        $superAdminRoleId = DB::table('roles')->where('name', 'super_admin')->value('id');

        if ($permissionId && $superAdminRoleId) {
            DB::table('permission_role')->updateOrInsert([
                'permission_id' => $permissionId,
                'role_id' => $superAdminRoleId,
            ]);
        }

        $this->seedExistingRelease('server', public_path('downloads/offline-server/AlignEx-Center-Server-win-unpacked.zip'), base_path('offline-server/package.json'));
        $this->seedExistingRelease('client_app', $this->latestClientAppInstaller(), base_path('offline-candidate-browser/package.json'));
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'manageAppReleases')->value('id');

        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        }

        DB::table('permissions')->where('name', 'manageAppReleases')->delete();
        Schema::dropIfExists('app_releases');
    }

    private function seedExistingRelease(string $artifact, ?string $path, string $packagePath): void
    {
        if (! $path || ! is_file($path) || filesize($path) <= 0) {
            return;
        }

        DB::table('app_releases')->updateOrInsert(
            ['artifact' => $artifact, 'version' => $this->packageVersion($packagePath)],
            [
                'filename' => basename($path),
                'file_path' => str_replace('\\', '/', ltrim(str_replace(public_path(), '', $path), '\\/')),
                'size_bytes' => filesize($path),
                'sha256' => hash_file('sha256', $path),
                'release_notes' => 'Seeded from existing compiled download.',
                'is_active' => true,
                'published_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    private function latestClientAppInstaller(): ?string
    {
        return collect([
            ...(glob(public_path('downloads/candidate-client/AlignEx-Client-App-Setup-*.exe')) ?: []),
            ...(glob(public_path('downloads/candidate-client/AlignEx-Candidate-Client-Setup-*.exe')) ?: []),
        ])
            ->filter(fn (string $path): bool => is_file($path))
            ->sortByDesc(fn (string $path): int => filemtime($path) ?: 0)
            ->first();
    }

    private function packageVersion(string $path): string
    {
        if (! is_file($path)) {
            return '0.0.0';
        }

        $package = json_decode((string) file_get_contents($path), true);

        return is_array($package) && is_string($package['version'] ?? null) ? $package['version'] : '0.0.0';
    }
};
