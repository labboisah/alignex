<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminRegistrationRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entity_type' => $this->entity_type,
            'entity_type_label' => str($this->entity_type)->replace('_', ' ')->title()->toString(),
            'entity_id' => $this->entity_id,
            'admin_name' => $this->admin_name,
            'admin_email' => $this->admin_email,
            'entity_name' => $this->entity_name,
            'entity_code' => $this->entity_code,
            'location' => $this->location,
            'capacity' => $this->capacity,
            'contact_person' => $this->contact_person,
            'phone' => $this->phone,
            'entity_email' => $this->entity_email,
            'address' => $this->address,
            'legal_registration_number' => $this->legal_registration_number,
            'website' => $this->website,
            'years_in_operation' => $this->years_in_operation,
            'operating_scope' => $this->operating_scope,
            'accreditation_body' => $this->accreditation_body,
            'accreditation_number' => $this->accreditation_number,
            'facility_summary' => $this->facility_summary,
            'exam_experience' => $this->exam_experience,
            'expected_candidates' => $this->expected_candidates,
            'status' => $this->status,
            'status_label' => str($this->status)->replace('_', ' ')->title()->toString(),
            'review_notes' => $this->review_notes,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
