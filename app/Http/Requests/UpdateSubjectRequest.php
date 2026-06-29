<?php

namespace App\Http\Requests;

use App\Models\Subject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('subject')) === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'alpha_dash'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in([Subject::STATUS_ACTIVE, Subject::STATUS_INACTIVE])],
        ];
    }
}
