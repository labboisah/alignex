<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdaptiveCandidateItemResource extends JsonResource
{
    // Only the lifecycle-issued current item may be passed to this resource.
    public function toArray(Request $request): array
    {
        $content = $this->resource->content;

        return [
            'question_id' => $content['question_id'],
            'question_text' => $content['stem'],
            'question_type' => $content['question_type'],
            'options' => array_map(fn ($option) => [
                'id' => $option['id'], 'label' => $option['label'], 'option_text' => $option['option_text'],
            ], $content['options']),
        ];
    }
}
