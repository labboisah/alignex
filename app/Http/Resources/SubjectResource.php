<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubjectResource extends JsonResource
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
            'school_class_id' => $this->school_class_id,
            'school_class_name' => $this->whenLoaded('schoolClass', fn () => $this->schoolClass?->name),
            'professional_school_id' => $this->professional_school_id,
            'professional_school_name' => $this->whenLoaded('professionalSchool', fn () => $this->professionalSchool?->name),
            'cbt_center_id' => $this->cbt_center_id,
            'cbt_center_name' => $this->whenLoaded('cbtCenter', fn () => $this->cbtCenter?->name),
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'status' => $this->status,
            'status_label' => str($this->status)->replace('_', ' ')->title()->toString(),
            'topics_count' => $this->whenCounted('topics'),
            'question_banks_count' => $this->whenCounted('questionBanks'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
