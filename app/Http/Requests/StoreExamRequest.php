<?php

namespace App\Http\Requests;

use App\Models\Exam;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        $exam = $this->route('exam');

        return $exam
            ? $this->user()?->can('update', $exam) === true
            : $this->user()?->can('create', Exam::class) === true;
    }

    public function rules(): array
    {
        $exam = $this->route('exam');

        return [
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'center_id' => ['nullable', 'integer', 'exists:centers,id'],
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'secondary_school_id' => ['nullable', 'integer', 'exists:secondary_schools,id'],
            'professional_school_id' => ['nullable', 'integer', 'exists:professional_schools,id'],
            'cbt_center_id' => ['nullable', 'integer', 'exists:cbt_centers,id'],
            'exam_owner_type' => ['nullable', Rule::in(Exam::OWNER_TYPES)],
            'exam_owner_id' => ['nullable', 'integer'],
            'exam_category' => ['nullable', Rule::in(Exam::CATEGORIES)],
            'title' => ['required', 'string', 'max:255'],
            'exam_code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('exams', 'code')->ignore($exam)],
            'exam_type' => ['required', Rule::in(['secondary', 'professional', 'recruitment'])],
            'mode' => ['required', Rule::in(['traditional', 'adaptive'])],
            'exam_mode' => ['nullable', Rule::in([Exam::MODE_TRADITIONAL, Exam::MODE_ADAPTIVE])],
            'delivery_mode' => ['required', Rule::in(['online', 'offline', 'hybrid'])],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'pass_mark' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in([
                Exam::STATUS_DRAFT,
                Exam::STATUS_SCHEDULED,
                Exam::STATUS_ACTIVE,
                Exam::STATUS_COMPLETED,
                Exam::STATUS_CANCELLED,
            ])],
            'subjects' => ['required', 'array', 'min:1'],
            'subjects.*.subject_id' => ['required', 'string', 'exists:subjects,id', 'distinct'],
            'subjects.*.number_of_questions' => ['required', 'integer', 'min:1', 'max:1000'],
            'subjects.*.marks_per_question' => ['required', 'numeric', 'min:0.01', 'max:1000'],
            'subjects.*.duration_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'subjects.*.difficulty_distribution' => ['nullable', 'array'],
            'settings.shuffle_questions' => ['required', 'boolean'],
            'settings.shuffle_options' => ['required', 'boolean'],
            'settings.show_result_immediately' => ['required', 'boolean'],
            'settings.allow_back_navigation' => ['required', 'boolean'],
            'settings.require_webcam' => ['required', 'boolean'],
            'settings.require_fullscreen' => ['required', 'boolean'],
            'settings.max_tab_switches' => ['required', 'integer', 'min:0', 'max:100'],
            'settings.negative_marking' => ['required', 'boolean'],
            'settings.negative_mark_value' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'settings.bind_device' => ['required', 'boolean'],
            'settings.allow_retake' => ['required', 'boolean'],
            'academic_session_id' => [Rule::prohibitedIf($this->isOrganizationExamRequest())],
            'term_id' => [Rule::prohibitedIf($this->isOrganizationExamRequest())],
            'academic_term_id' => [Rule::prohibitedIf($this->isOrganizationExamRequest())],
            'school_class_id' => [Rule::prohibitedIf($this->isOrganizationExamRequest())],
            'class_arm_id' => [Rule::prohibitedIf($this->isOrganizationExamRequest())],
            'programme_id' => [Rule::prohibitedIf($this->isOrganizationExamRequest())],
            'course_id' => [Rule::prohibitedIf($this->isOrganizationExamRequest())],
            'module_id' => [Rule::prohibitedIf($this->isOrganizationExamRequest())],
            'training_batch_id' => [Rule::prohibitedIf($this->isOrganizationExamRequest())],
        ];
    }

    private function isOrganizationExamRequest(): bool
    {
        return $this->user()?->organization_id !== null
            || $this->route('organization') !== null
            || $this->input('exam_owner_type') === Exam::OWNER_ORGANIZATION
            || filled($this->input('organization_id'));
    }
}
