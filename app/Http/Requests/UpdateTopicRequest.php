<?php

namespace App\Http\Requests;

use App\Models\Topic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('topic')) === true;
    }

    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'string', 'exists:subjects,id'],
            'parent_id' => ['nullable', 'string', 'exists:topics,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'alpha_dash'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in([Topic::STATUS_ACTIVE, Topic::STATUS_INACTIVE])],
        ];
    }
}
