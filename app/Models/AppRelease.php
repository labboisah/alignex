<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'artifact',
    'version',
    'filename',
    'file_path',
    'size_bytes',
    'sha256',
    'release_notes',
    'is_active',
    'published_at',
])]
class AppRelease extends Model
{
    use HasFactory;

    public const ARTIFACT_SERVER = 'server';
    public const ARTIFACT_CLIENT_APP = 'client_app';

    public const ARTIFACTS = [
        self::ARTIFACT_SERVER,
        self::ARTIFACT_CLIENT_APP,
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'is_active' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function scopeLatestActiveFor(Builder $query, string $artifact): Builder
    {
        return $query
            ->where('artifact', $artifact)
            ->where('is_active', true)
            ->orderByDesc('published_at')
            ->orderByDesc('created_at');
    }

    public function absolutePath(): string
    {
        return public_path($this->file_path);
    }
}
