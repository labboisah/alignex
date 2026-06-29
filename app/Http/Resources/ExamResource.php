<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'organization_name' => $this->whenLoaded('organization', fn () => $this->organization?->name),
            'center_id' => $this->center_id,
            'center_name' => $this->whenLoaded('center', fn () => $this->center?->name),
            'school_id' => $this->school_id,
            'school_name' => $this->whenLoaded('school', fn () => $this->school?->name),
            'title' => $this->title,
            'exam_code' => $this->code,
            'exam_type' => $this->whenLoaded('examType', fn () => $this->examType?->code),
            'exam_type_label' => $this->whenLoaded('examType', fn () => $this->examType?->name),
            'mode' => $this->mode,
            'delivery_mode' => $this->delivery_mode,
            'start_at' => $this->starts_at?->format('Y-m-d\TH:i'),
            'end_at' => $this->ends_at?->format('Y-m-d\TH:i'),
            'duration_minutes' => $this->duration_minutes,
            'total_marks' => $this->total_marks,
            'pass_mark' => $this->pass_mark,
            'status' => $this->status,
            'status_label' => str($this->status)->replace('_', ' ')->title()->toString(),
            'settings' => $this->settings ?? [],
            'subjects' => $this->whenLoaded('examSubjects', fn () => $this->examSubjects
                ->sortBy('display_order')
                ->values()
                ->map(fn ($examSubject) => [
                    'id' => $examSubject->id,
                    'subject_id' => $examSubject->subject_id,
                    'subject_name' => $examSubject->subject?->name,
                    'number_of_questions' => $examSubject->question_count,
                    'marks_per_question' => $examSubject->marks_per_question,
                    'duration_minutes' => $examSubject->duration_minutes,
                    'total_marks' => $examSubject->total_marks,
                    'difficulty_distribution' => $examSubject->difficulty_distribution,
                ])),
            'subjects_count' => $this->whenCounted('examSubjects'),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
