<?php

namespace App\Http\Requests;

use App\Models\Question;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('question')) === true;
    }

    public function rules(): array
    {
        return [
            'question_bank_id' => ['required', 'string', 'exists:question_banks,id'],
            'subject_id' => ['required', 'string', 'exists:subjects,id'],
            'topic_id' => ['nullable', 'string', 'exists:topics,id'],
            'difficulty' => ['required', Rule::in(['easy', 'medium', 'hard'])],
            'marks' => ['required', 'numeric', 'min:0.01', 'max:1000'],
            'stem' => ['required', 'string', 'max:10000'],
            'image' => ['nullable', 'image', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
            'explanation' => ['nullable', 'string', 'max:10000'],
            'status' => ['required', Rule::in([
                Question::STATUS_DRAFT,
                Question::STATUS_REVIEW,
                Question::STATUS_APPROVED,
                Question::STATUS_REJECTED,
                Question::STATUS_ARCHIVED,
            ])],
            'options' => ['required', 'array', 'min:2', 'max:5'],
            'options.*.label' => ['required', Rule::in(['A', 'B', 'C', 'D', 'E']), 'distinct'],
            'options.*.option_text' => ['nullable', 'string', 'max:5000'],
            'options.*.is_correct' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $filledOptions = collect($this->input('options', []))
                ->filter(fn (array $option) => filled($option['option_text'] ?? null));

            if ($filledOptions->count() < 2) {
                $validator->errors()->add('options', 'At least two options are required.');
            }

            if ($filledOptions->filter(fn (array $option) => $this->isTruthy($option['is_correct'] ?? false))->count() !== 1) {
                $validator->errors()->add('options', 'Choose exactly one correct answer.');
            }
        });
    }

    private function isTruthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'on'], true);
    }
}
