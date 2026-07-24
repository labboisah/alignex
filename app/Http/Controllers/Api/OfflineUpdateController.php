<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppRelease;
use App\Models\User;
use App\Services\OfflineActivationGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OfflineUpdateController extends Controller
{
    public function __construct(private readonly OfflineActivationGuard $activationGuard)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->activationGuard->requireActive($request);

        if (! $this->authenticateSyncAdmin($request)) {
            return response()->json(['message' => 'Offline sync admin credentials are invalid.'], 401);
        }

        return response()->json([
            'updates' => [
                'server' => $this->publicArtifact($request, 'server'),
                'client_app' => $this->publicArtifact($request, 'client_app'),
            ],
        ]);
    }

    public function download(Request $request, string $artifact): BinaryFileResponse|JsonResponse
    {
        $this->activationGuard->requireActive($request);

        if (! $this->authenticateSyncAdmin($request)) {
            return response()->json(['message' => 'Offline sync admin credentials are invalid.'], 401);
        }

        $metadata = $this->releaseArtifact($request, $artifact) ?? $this->artifact($request, $artifact);

        if (! $metadata) {
            return response()->json(['message' => 'Unknown offline update artifact.'], 404);
        }

        return response()->download($metadata['path'], $metadata['filename'], [
            'Content-Type' => $artifact === 'server' ? 'application/zip' : 'application/octet-stream',
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function artifact(Request $request, string $artifact): ?array
    {
        $path = match ($artifact) {
            'server' => $this->latestServerPackage(),
            'client_app' => $this->latestClientAppInstaller(),
            default => null,
        };

        if (! $path || ! is_file($path) || filesize($path) <= 0) {
            return null;
        }

        $filename = basename($path);

        return [
            'artifact' => $artifact,
            'version' => $this->artifactVersion($artifact),
            'filename' => $filename,
            'size_bytes' => filesize($path),
            'sha256' => hash_file('sha256', $path),
            'download_url' => $request->root()."/api/offline/updates/{$artifact}/download",
            'updated_at' => date(DATE_ATOM, filemtime($path) ?: time()),
            'path' => $path,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function releaseArtifact(Request $request, string $artifact): ?array
    {
        if (! Schema::hasTable('app_releases') || ! in_array($artifact, AppRelease::ARTIFACTS, true)) {
            return null;
        }

        $release = AppRelease::query()->latestActiveFor($artifact)->first();

        if (! $release || ! is_file($release->absolutePath()) || filesize($release->absolutePath()) <= 0) {
            return null;
        }

        return [
            'artifact' => $release->artifact,
            'version' => $release->version,
            'filename' => $release->filename,
            'size_bytes' => $release->size_bytes,
            'sha256' => $release->sha256,
            'download_url' => $request->root()."/api/offline/updates/{$artifact}/download",
            'updated_at' => ($release->published_at ?? $release->updated_at)->toISOString(),
            'path' => $release->absolutePath(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function publicArtifact(Request $request, string $artifact): ?array
    {
        $metadata = $this->releaseArtifact($request, $artifact) ?? $this->artifact($request, $artifact);

        if (! $metadata) {
            return null;
        }

        unset($metadata['path']);

        return $metadata;
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

    private function latestServerPackage(): ?string
    {
        return collect([
            public_path('downloads/offline-server/AlignEx-Center-Server-win-unpacked.zip'),
            ...(glob(public_path('downloads/offline-server/AlignEx-Center-Server*.zip')) ?: []),
            ...(glob($this->appPath('offline_server_path', 'dist-release/AlignEx-Center-Server*.zip')) ?: []),
            ...(glob($this->appPath('offline_server_path', 'release/AlignEx-Center-Server*.zip')) ?: []),
        ])
            ->filter(fn (string $path): bool => is_file($path) && filesize($path) > 0)
            ->sortByDesc(fn (string $path): int => filemtime($path) ?: 0)
            ->first();
    }

    private function artifactVersion(string $artifact): string
    {
        $packagePath = match ($artifact) {
            'server' => $this->appPath('offline_server_path', 'package.json'),
            'client_app' => $this->appPath('candidate_app_path', 'package.json'),
            default => null,
        };

        if (! $packagePath || ! is_file($packagePath)) {
            return '0.0.0';
        }

        $package = json_decode((string) file_get_contents($packagePath), true);

        return is_array($package) && is_string($package['version'] ?? null) ? $package['version'] : '0.0.0';
    }

    private function appPath(string $key, string $childPath): string
    {
        return rtrim((string) config("alignex.apps.{$key}"), '\\/').DIRECTORY_SEPARATOR.$childPath;
    }

    private function authenticateSyncAdmin(Request $request): ?User
    {
        $email = trim((string) $request->header('X-AlignEx-Admin-Email'));
        $password = (string) $request->header('X-AlignEx-Admin-Password');

        if ($email === '' || $password === '') {
            return null;
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user || ! $user->isPortalUser() || ! Hash::check($password, $user->password)) {
            return null;
        }

        return $user;
    }
}
