<?php

namespace App\Http\Requests;

use App\Models\Candidate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Candidate::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();
        $organizationRules = ['nullable', 'integer', 'exists:organizations,id'];
        $schoolRules = ['nullable', 'integer', 'exists:schools,id'];
        $centerRules = ['nullable', 'integer', 'exists:centers,id'];

        if ($user?->isSuperAdmin()) {
            $organizationRules[] = 'required_without_all:school_id,center_id';
            $schoolRules[] = 'required_without_all:organization_id,center_id';
            $centerRules[] = 'required_without_all:organization_id,school_id';
        }

        return [
            'organization_id' => $organizationRules,
            'school_id' => $schoolRules,
            'center_id' => $centerRules,
            'full_name' => ['required_without:first_name', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'registration_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('candidates', 'candidate_number')
                    ->where(fn ($query) => $query
                        ->when($user?->isOrganizationAdmin() || $user?->organization_id, fn ($query) => $query->where('organization_id', $user->organization_id))
                        ->when($user?->isSchoolAdmin() || $user?->school_id, fn ($query) => $query->where('school_id', $user->school_id))
                        ->when($user?->isCenterAdmin() || $user?->center_id, fn ($query) => $query->where('center_id', $user->center_id))
                        ->when($user?->isSuperAdmin() && $this->organization_id, fn ($query) => $query->where('organization_id', $this->organization_id))
                        ->when($user?->isSuperAdmin() && $this->school_id, fn ($query) => $query->where('school_id', $this->school_id))
                        ->when($user?->isSuperAdmin() && $this->center_id, fn ($query) => $query->where('center_id', $this->center_id))),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'status' => ['required', Rule::in([Candidate::STATUS_ACTIVE, Candidate::STATUS_INACTIVE, Candidate::STATUS_SUSPENDED])],
        ];
    }
}
