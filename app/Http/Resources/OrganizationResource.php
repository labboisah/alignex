<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'organization_type' => $this->organization_type,
            'organization_type_label' => $this->organization_type ? str($this->organization_type)->replace('_', ' ')->title()->toString() : 'N/A',
            'description' => $this->description,
            'logo' => $this->logo,
            'website' => $this->website,
            'contact_person' => $this->contact_person,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'status' => $this->status,
            'status_label' => str($this->status)->replace('_', ' ')->title()->toString(),
            'exams_count' => $this->whenCounted('exams'),
            'candidates_count' => $this->whenCounted('candidates'),
            'question_banks_count' => $this->whenCounted('questionBanks'),
            'secondary_schools_count' => $this->whenCounted('secondarySchools'),
            'professional_schools_count' => $this->whenCounted('professionalSchools'),
            'cbt_centers_count' => $this->whenCounted('cbtCenters'),
            'recent_exams' => $this->whenLoaded('exams', fn () => $this->exams->take(5)->map(fn ($exam) => [
                'id' => $exam->id,
                'title' => $exam->title,
                'code' => $exam->code,
                'category' => $exam->exam_category,
                'mode' => $exam->exam_mode ?? $exam->mode,
                'status' => $exam->status,
            ])),
            'recent_results' => $this->whenLoaded('exams', fn () => $this->exams
                ->flatMap(fn ($exam) => $exam->attempts ?? collect())
                ->take(5)
                ->map(fn ($attempt) => [
                    'id' => $attempt->id,
                    'candidate_name' => trim(($attempt->candidate?->first_name ?? '').' '.($attempt->candidate?->last_name ?? '')),
                    'exam_title' => $attempt->exam?->title,
                    'score' => $attempt->score,
                    'submitted_at' => $attempt->submitted_at?->toISOString(),
                ])),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
