<?php

namespace App\Http\Resources;

use App\Support\AccessControl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'role_label' => AccessControl::roleLabel($this->role),
            'status' => $this->status ?? 'active',
            'status_label' => str($this->status ?? 'active')->headline()->toString(),
            'organization_id' => $this->organization_id,
            'organization_name' => $this->organization?->name,
            'secondary_school_id' => $this->secondary_school_id,
            'secondary_school_name' => $this->secondarySchool?->name,
            'professional_school_id' => $this->professional_school_id,
            'professional_school_name' => $this->professionalSchool?->name,
            'cbt_center_id' => $this->cbt_center_id,
            'cbt_center_name' => $this->cbtCenter?->name,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
