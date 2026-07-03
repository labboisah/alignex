<?php

namespace App\Http\Requests;

use App\Models\QuestionBank;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuestionBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('questionBank')) === true;
    }

    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'string', 'exists:subjects,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'alpha_dash'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in([QuestionBank::STATUS_DRAFT, QuestionBank::STATUS_ACTIVE, QuestionBank::STATUS_ARCHIVED])],
        ];
    }
}
