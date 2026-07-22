<?php

namespace App\Http\Controllers;

use App\Models\AppRelease;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OfflineServerDownloadController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        abort_unless($request->user(), 403);

        $release = Schema::hasTable('app_releases')
            ? AppRelease::query()->latestActiveFor(AppRelease::ARTIFACT_SERVER)->first()
            : null;

        if ($release && is_file($release->absolutePath()) && filesize($release->absolutePath()) > 0) {
            return response()->download($release->absolutePath(), $release->filename, [
                'Content-Type' => 'application/zip',
            ]);
        }

        $path = $this->latestServerPackage();

        abort_unless($path, 404, 'Offline server package has not been compiled yet.');

        return response()->download($path, basename($path), [
            'Content-Type' => 'application/zip',
        ]);
    }

    private function latestServerPackage(): ?string
    {
        return collect([
            public_path('downloads/offline-server/AlignEx-Center-Server-win-unpacked.zip'),
            ...(glob(public_path('downloads/offline-server/AlignEx-Center-Server*.zip')) ?: []),
            ...(glob($this->offlineServerPath('dist-release/AlignEx-Center-Server*.zip')) ?: []),
            ...(glob($this->offlineServerPath('release/AlignEx-Center-Server*.zip')) ?: []),
        ])
            ->filter(fn (string $path): bool => is_file($path) && filesize($path) > 0)
            ->sortByDesc(fn (string $path): int => filemtime($path) ?: 0)
            ->first();
    }

    private function offlineServerPath(string $childPath): string
    {
        return rtrim((string) config('alignex.apps.offline_server_path'), '\\/').DIRECTORY_SEPARATOR.$childPath;
    }
}
