<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CandidateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $photoPath = $this->metadata['photo_path'] ?? null;

        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'organization_name' => $this->whenLoaded('organization', fn () => $this->organization?->name),
            'school_id' => $this->school_id,
            'school_name' => $this->whenLoaded('school', fn () => $this->school?->name),
            'center_id' => $this->center_id,
            'center_name' => $this->whenLoaded('center', fn () => $this->center?->name),
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => trim($this->first_name.' '.$this->last_name),
            'registration_number' => $this->candidate_number,
            'email' => $this->email,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'photo_url' => $photoPath ? Storage::url($photoPath) : null,
            'status' => $this->status,
            'status_label' => str($this->status)->replace('_', ' ')->title()->toString(),
            'assigned_exams_count' => $this->whenCounted('assignedExams'),
            'assigned_exams' => $this->whenLoaded('assignedExams', fn () => $this->assignedExams->map(fn ($exam) => [
                'id' => $exam->id,
                'title' => $exam->title,
                'exam_code' => $exam->code,
                'status' => $exam->pivot?->status,
            ])),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
