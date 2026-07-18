<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppReleaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'artifact' => $this->artifact,
            'artifact_label' => $this->artifact === 'server' ? 'Offline Server' : 'Client App',
            'version' => $this->version,
            'filename' => $this->filename,
            'file_path' => $this->file_path,
            'size_bytes' => $this->size_bytes,
            'formatted_size' => $this->formattedSize(),
            'sha256' => $this->sha256,
            'release_notes' => $this->release_notes,
            'is_active' => $this->is_active,
            'published_at' => $this->published_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'download_url' => route('app-releases.download', $this->resource),
        ];
    }

    private function formattedSize(): string
    {
        $bytes = (int) $this->size_bytes;

        if ($bytes >= 1_073_741_824) {
            return number_format($bytes / 1_073_741_824, 2).' GB';
        }

        if ($bytes >= 1_048_576) {
            return number_format($bytes / 1_048_576, 2).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }
}
