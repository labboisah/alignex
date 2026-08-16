<?php

namespace App\Support;

use App\Models\AppRelease;

class AppArtifactFile
{
    private const MIN_DOWNLOAD_BYTES = 1024 * 1024;

    public static function isDownloadable(string $path, string $artifact): bool
    {
        if (! is_file($path) || filesize($path) < self::MIN_DOWNLOAD_BYTES) {
            return false;
        }

        if (self::isGitLfsPointer($path)) {
            return false;
        }

        return match ($artifact) {
            AppRelease::ARTIFACT_SERVER => self::hasZipSignature($path),
            AppRelease::ARTIFACT_CLIENT_APP => self::hasWindowsExecutableSignature($path),
            default => false,
        };
    }

    public static function isGitLfsPointer(string $path): bool
    {
        $handle = @fopen($path, 'rb');

        if (! $handle) {
            return false;
        }

        $head = fread($handle, 128) ?: '';
        fclose($handle);

        return str_starts_with($head, 'version https://git-lfs.github.com/spec/v1');
    }

    private static function hasZipSignature(string $path): bool
    {
        return self::startsWithBytes($path, "PK\x03\x04")
            || self::startsWithBytes($path, "PK\x05\x06")
            || self::startsWithBytes($path, "PK\x07\x08");
    }

    private static function hasWindowsExecutableSignature(string $path): bool
    {
        return self::startsWithBytes($path, 'MZ');
    }

    private static function startsWithBytes(string $path, string $signature): bool
    {
        $handle = @fopen($path, 'rb');

        if (! $handle) {
            return false;
        }

        $head = fread($handle, strlen($signature)) ?: '';
        fclose($handle);

        return $head === $signature;
    }
}
