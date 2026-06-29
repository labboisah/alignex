<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class QuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'question_bank_id' => $this->question_bank_id,
            'question_bank_name' => $this->whenLoaded('questionBank', fn () => $this->questionBank?->name),
            'subject_id' => $this->subject_id,
            'subject_name' => $this->whenLoaded('subject', fn () => $this->subject?->name),
            'topic_id' => $this->topic_id,
            'topic_name' => $this->whenLoaded('topic', fn () => $this->topic?->name),
            'question_type' => $this->question_type,
            'stem' => $this->stem,
            'image_path' => $this->image_path,
            'image_url' => $this->image_path ? Storage::url($this->image_path) : null,
            'explanation' => $this->explanation,
            'difficulty' => $this->difficulty,
            'marks' => $this->marks,
            'negative_marks' => $this->negative_marks,
            'status' => $this->status,
            'status_label' => str($this->status)->replace('_', ' ')->title()->toString(),
            'options' => $this->whenLoaded('options', fn () => $this->options
                ->sortBy('display_order')
                ->values()
                ->map(fn ($option) => [
                    'id' => $option->id,
                    'label' => $option->label,
                    'option_text' => $option->option_text,
                    'display_order' => $option->display_order,
                    'is_correct' => $option->is_correct,
                ])),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
