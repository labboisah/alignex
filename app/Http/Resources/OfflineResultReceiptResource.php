<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfflineResultReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return collect($this->resource)->only([
            'id', 'upload_id', 'attempt_id', 'status', 'local_score', 'official_score',
            'score_difference', 'legacy_package', 'visibility', 'result_url', 'received_at',
        ])->all();
    }
}
