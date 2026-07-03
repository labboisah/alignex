<?php

namespace App\Http\Requests;

use App\Models\Topic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Topic::class) === true;
    }

    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'string', 'exists:subjects,id'],
            'parent_id' => ['nullable', 'string', 'exists:topics,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'alpha_dash'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in([Topic::STATUS_ACTIVE, Topic::STATUS_INACTIVE])],
        ];
    }
}
