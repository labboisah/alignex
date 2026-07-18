<?php

namespace App\Http\Controllers;

use App\Models\AppRelease;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CandidateClientDownloadController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        abort_unless($request->user(), 403);

        $release = Schema::hasTable('app_releases')
            ? AppRelease::query()->latestActiveFor(AppRelease::ARTIFACT_CLIENT_APP)->first()
            : null;

        if ($release && is_file($release->absolutePath()) && filesize($release->absolutePath()) > 0) {
            return response()->download($release->absolutePath(), $release->filename, [
                'Content-Type' => 'application/octet-stream',
            ]);
        }

        $installer = collect([
            ...(glob(public_path('downloads/candidate-client/AlignEx-Client-App-Setup-*.exe')) ?: []),
            ...(glob(public_path('downloads/candidate-client/AlignEx-Candidate-Client-Setup-*.exe')) ?: []),
            ...(glob($this->candidateAppPath('dist-release/AlignEx-Client-App-Setup-*.exe')) ?: []),
            ...(glob($this->candidateAppPath('dist-release/AlignEx-Candidate-Client-Setup-*.exe')) ?: []),
        ])
            ->filter(fn (string $path): bool => is_file($path))
            ->sortByDesc(fn (string $path): int => filemtime($path) ?: 0)
            ->first();

        abort_unless($installer, 404, 'Client app installer has not been compiled yet.');

        return response()->download($installer, basename($installer), [
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    private function candidateAppPath(string $childPath): string
    {
        return rtrim((string) config('alignex.apps.candidate_app_path'), '\\/').DIRECTORY_SEPARATOR.$childPath;
    }
}
