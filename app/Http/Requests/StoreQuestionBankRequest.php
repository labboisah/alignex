<?php

namespace App\Http\Requests;

use App\Models\QuestionBank;
use App\Services\CurrentContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuestionBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', QuestionBank::class) === true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'center_id' => ['nullable', 'integer', 'exists:centers,id'],
            'subject_id' => [$this->isInstitutionContext() ? 'nullable' : 'required', 'string', 'exists:subjects,id'],
            'course_id' => [$this->isInstitutionContext() ? 'required' : 'nullable', 'integer', 'exists:courses,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'alpha_dash'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in([QuestionBank::STATUS_DRAFT, QuestionBank::STATUS_ACTIVE, QuestionBank::STATUS_ARCHIVED])],
        ];
    }

    private function isInstitutionContext(): bool
    {
        $context = app(CurrentContextService::class)->current($this->user());

        return ($context['type'] ?? null) === 'institution'
            || $this->route('institution') !== null
            || $this->user()?->institution_id !== null;
    }
}
