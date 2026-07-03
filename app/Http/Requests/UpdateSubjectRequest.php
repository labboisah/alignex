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
            'code' => ['nullable', 'string', 'max:50', 'alpha_dash'],
            'school_class_id' => ['nullable', 'exists:school_classes,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in([Subject::STATUS_ACTIVE, Subject::STATUS_INACTIVE])],
        ];
    }
}
