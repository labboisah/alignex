<?php

namespace App\Http\Controllers;

use App\Http\Resources\AppReleaseResource;
use App\Models\AppRelease;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AppReleaseController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('AppReleases/Index', [
            'releases' => AppReleaseResource::collection(
                AppRelease::query()
                    ->orderBy('artifact')
                    ->orderByDesc('published_at')
                    ->orderByDesc('created_at')
                    ->get()
            ),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        AppRelease::create($this->validated($request));

        return redirect()
            ->route('app-releases.index')
            ->with('success', 'App release created.');
    }

    public function update(Request $request, AppRelease $appRelease): RedirectResponse
    {
        $appRelease->update($this->validated($request, $appRelease));

        return redirect()
            ->route('app-releases.index')
            ->with('success', 'App release updated.');
    }

    public function destroy(AppRelease $appRelease): RedirectResponse
    {
        $appRelease->delete();

        return redirect()
            ->route('app-releases.index')
            ->with('success', 'App release deleted.');
    }

    public function download(AppRelease $appRelease): BinaryFileResponse
    {
        abort_unless(is_file($appRelease->absolutePath()) && filesize($appRelease->absolutePath()) > 0, 404, 'Release file is missing.');

        return response()->download($appRelease->absolutePath(), $appRelease->filename, [
            'Content-Type' => $appRelease->artifact === AppRelease::ARTIFACT_SERVER ? 'application/zip' : 'application/octet-stream',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?AppRelease $release = null): array
    {
        $validated = $request->validate([
            'artifact' => ['required', Rule::in(AppRelease::ARTIFACTS)],
            'version' => ['required', 'string', 'max:40', 'regex:/^\d+(?:\.\d+){0,3}(?:[-+][A-Za-z0-9.-]+)?$/', Rule::unique(AppRelease::class, 'version')->where('artifact', $request->input('artifact'))->ignore($release)],
            'file' => ['nullable', 'file', 'max:512000'],
            'file_path' => ['nullable', 'string', 'max:500'],
            'release_notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],
            'published_at' => ['nullable', 'date'],
        ]);

        if (! $request->hasFile('file') && blank($validated['file_path'] ?? null) && ! $release) {
            throw ValidationException::withMessages([
                'file' => 'Upload a release file or provide a public file path.',
            ]);
        }

        if ($request->hasFile('file')) {
            $this->applyUploadedFile($validated, $request->file('file'), $validated['artifact']);
        } elseif (filled($validated['file_path'] ?? null)) {
            $this->applyExistingFilePath($validated, $validated['file_path']);
        } elseif ($release) {
            $validated['filename'] = $release->filename;
            $validated['file_path'] = $release->file_path;
            $validated['size_bytes'] = $release->size_bytes;
            $validated['sha256'] = $release->sha256;
        }

        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);
        $validated['published_at'] = $validated['published_at'] ?? now();

        unset($validated['file']);

        return $validated;
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function applyUploadedFile(array &$validated, UploadedFile $file, string $artifact): void
    {
        $folder = $artifact === AppRelease::ARTIFACT_SERVER ? 'downloads/offline-server' : 'downloads/candidate-client';
        $extension = $file->getClientOriginalExtension() ?: ($artifact === AppRelease::ARTIFACT_SERVER ? 'zip' : 'exe');
        $filename = $artifact === AppRelease::ARTIFACT_SERVER
            ? 'AlignEx-Center-Server-'.$validated['version'].'.'.$extension
            : 'AlignEx-Client-App-Setup-'.$validated['version'].'.'.$extension;

        File::ensureDirectoryExists(public_path($folder));

        $file->move(public_path($folder), $filename);

        $validated['filename'] = $filename;
        $validated['file_path'] = $folder.'/'.$filename;
        $validated['size_bytes'] = filesize(public_path($validated['file_path'])) ?: 0;
        $validated['sha256'] = hash_file('sha256', public_path($validated['file_path']));
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function applyExistingFilePath(array &$validated, string $filePath): void
    {
        $relativePath = str_replace('\\', '/', ltrim($filePath, '\\/'));
        $absolutePath = public_path($relativePath);

        if (! is_file($absolutePath) || filesize($absolutePath) <= 0) {
            throw ValidationException::withMessages([
                'file_path' => 'The selected release file does not exist under the public directory.',
            ]);
        }

        $validated['filename'] = basename($absolutePath);
        $validated['file_path'] = $relativePath;
        $validated['size_bytes'] = filesize($absolutePath) ?: 0;
        $validated['sha256'] = hash_file('sha256', $absolutePath);
    }
}
