<?php

namespace App\Http\Requests;

use App\Models\Subject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Subject::class) === true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'center_id' => ['nullable', 'integer', 'exists:centers,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'alpha_dash'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in([Subject::STATUS_ACTIVE, Subject::STATUS_INACTIVE])],
        ];
    }
}
