<?php

namespace App\Http\Requests;

use App\Models\Candidate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('candidate')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Candidate $candidate */
        $candidate = $this->route('candidate');

        return [
            'full_name' => ['required_without:first_name', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'registration_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('candidates', 'candidate_number')
                    ->ignore($candidate)
                    ->where(fn ($query) => $query
                        ->when($candidate->organization_id, fn ($query) => $query->where('organization_id', $candidate->organization_id))
                        ->when($candidate->school_id, fn ($query) => $query->where('school_id', $candidate->school_id))
                        ->when($candidate->center_id, fn ($query) => $query->where('center_id', $candidate->center_id))),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'status' => ['required', Rule::in([Candidate::STATUS_ACTIVE, Candidate::STATUS_INACTIVE, Candidate::STATUS_SUSPENDED])],
        ];
    }
}
