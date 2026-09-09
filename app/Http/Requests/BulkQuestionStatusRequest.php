<?php

namespace App\Http\Requests;

use App\Models\Question;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkQuestionStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Question::class) === true;
    }

    public function rules(): array
    {
        return [
            'question_ids' => ['required', 'array', 'min:1'],
            'question_ids.*' => ['required', 'string', 'distinct'],
            'status' => ['required', Rule::in([Question::STATUS_DRAFT, Question::STATUS_REVIEW, Question::STATUS_APPROVED, Question::STATUS_REJECTED, Question::STATUS_ARCHIVED])],
        ];
    }
}
