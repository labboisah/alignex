<?php

namespace App\Http\Requests;

use App\Models\Question;
use Illuminate\Foundation\Http\FormRequest;

class ImportQuestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Question::class) === true
            || ($this->route('professionalSchool') !== null && $this->user()?->hasPermission('manageSchools'));
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:4096'],
            'question_bank_id' => ['required', 'string', 'exists:question_banks,id'],
            'subject_id' => ['nullable', 'string', 'exists:subjects,id'],
            'topic_id' => ['nullable', 'string', 'exists:topics,id'],
            'status' => ['sometimes', ...(new StoreQuestionRequest)->rules()['status']],
        ];
    }
}
