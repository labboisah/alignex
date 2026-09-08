<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdaptiveCandidateItemResource extends JsonResource
{
    // Contract only: no candidate endpoint issues pool items until the Phase 3 state machine exists.
    public function toArray(Request $request): array
    {
        $content = $this->resource->content;

        return [
            'question_id' => $content['question_id'],
            'question_text' => $content['stem'],
            'options' => array_map(fn ($option) => [
                'id' => $option['id'], 'label' => $option['label'], 'option_text' => $option['option_text'],
            ], $content['options']),
        ];
    }
}
