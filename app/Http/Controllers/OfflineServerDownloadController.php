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

        $path = public_path('downloads/offline-server/AlignEx-Center-Server-win-unpacked.zip');

        abort_unless(is_file($path) && filesize($path) > 0, 404, 'Offline server package has not been compiled yet.');

        return response()->download($path, 'AlignEx-Center-Server-win-unpacked.zip', [
            'Content-Type' => 'application/zip',
        ]);
    }
}
