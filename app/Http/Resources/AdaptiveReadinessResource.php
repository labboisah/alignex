<?php

namespace App\Http\Resources;

use App\Services\AdaptivePresentationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdaptiveReadinessResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ready' => $this->resource['ready'],
            'warnings' => $this->resource['warnings'],
            'areas' => array_map(fn ($area) => [...$area, 'label' => app(AdaptivePresentationService::class)->areaLabel($area)], $this->resource['areas']),
            'item_count' => $this->resource['item_count'],
        ];
    }
}
