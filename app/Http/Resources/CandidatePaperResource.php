<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CandidatePaperResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $question = $this->resource->relationLoaded('question') ? $this->question : null;
        $optionOrder = collect($this->option_order ?? []);

        return [
            'id' => $this->id,
            'question_id' => $this->question_id,
            'question_order' => $this->question_order,
            'subject_id' => $question?->subject_id,
            'subject_name' => $question?->relationLoaded('subject') ? $question->subject?->name : null,
            'question_text' => $question?->stem,
            'image_url' => $question?->image_path ? Storage::url($question->image_path) : null,
            'marks' => $question?->marks,
            'options' => $question?->relationLoaded('options')
                ? $question->options
                    ->sortBy(fn ($option) => $optionOrder->search($option->id) === false ? $option->display_order : $optionOrder->search($option->id))
                    ->values()
                    ->map(fn ($option) => [
                        'id' => $option->id,
                        'label' => $option->label,
                        'option_text' => $option->option_text,
                    ])
                : [],
        ];
    }
}
