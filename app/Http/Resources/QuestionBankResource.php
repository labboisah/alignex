<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionBankResource extends JsonResource
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
            'school_id' => $this->school_id,
            'school_name' => $this->whenLoaded('school', fn () => $this->school?->name),
            'center_id' => $this->center_id,
            'center_name' => $this->whenLoaded('center', fn () => $this->center?->name),
            'secondary_school_id' => $this->secondary_school_id,
            'secondary_school_name' => $this->whenLoaded('secondarySchool', fn () => $this->secondarySchool?->name),
            'professional_school_id' => $this->professional_school_id,
            'professional_school_name' => $this->whenLoaded('professionalSchool', fn () => $this->professionalSchool?->name),
            'cbt_center_id' => $this->cbt_center_id,
            'cbt_center_name' => $this->whenLoaded('cbtCenter', fn () => $this->cbtCenter?->name),
            'programme_id' => $this->programme_id,
            'programme_name' => $this->whenLoaded('programme', fn () => $this->programme?->name),
            'course_id' => $this->course_id,
            'course_name' => $this->whenLoaded('course', fn () => $this->course?->name),
            'module_id' => $this->module_id,
            'module_name' => $this->whenLoaded('module', fn () => $this->module?->name),
            'subject_id' => $this->subject_id,
            'subject_name' => $this->whenLoaded('subject', fn () => $this->subject?->name),
            'subject_code' => $this->whenLoaded('subject', fn () => $this->subject?->code),
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'status' => $this->status,
            'status_label' => str($this->status)->replace('_', ' ')->title()->toString(),
            'questions_count' => $this->whenCounted('questions'),
            'can' => [
                'view' => $request->user()?->can('view', $this->resource) ?? false,
                'update' => $request->user()?->can('update', $this->resource) ?? false,
                'delete' => $request->user()?->can('delete', $this->resource) ?? false,
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
