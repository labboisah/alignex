<?php

namespace App\Http\Requests;

use App\Models\Exam;
use App\Models\StudentGroup;
use App\Models\TrainingBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
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
            'exam_type' => ['required', Rule::in(['secondary', 'professional', 'recruitment', 'assessment', 'certification', 'practice', 'general'])],
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
            'subjects.*.question_bank_id' => ['nullable', 'exists:question_banks,id'],
            'subjects.*.question_bank_ids' => ['nullable', 'array'],
            'subjects.*.question_bank_ids.*' => ['nullable', 'exists:question_banks,id'],
            'subjects.*.number_of_questions' => ['required', 'integer', 'min:1', 'max:1000'],
            'subjects.*.marks_per_question' => ['required', 'numeric', 'min:0.01', 'max:1000'],
            'subjects.*.duration_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'subjects.*.difficulty_distribution' => ['nullable', 'array'],
            'question_bank_id' => ['nullable', 'exists:question_banks,id'],
            'candidate_ids' => [Rule::excludeIf($this->isSecondaryExamRequest() || $this->isProfessionalExamRequest()), 'nullable', 'array'],
            'candidate_ids.*' => [Rule::excludeIf($this->isSecondaryExamRequest() || $this->isProfessionalExamRequest()), 'string', 'exists:candidates,id', 'distinct'],
            'candidate_group_id' => [Rule::excludeIf($this->isSecondaryExamRequest() || $this->isProfessionalExamRequest()), 'nullable', 'string', 'max:100', 'exists:candidate_groups,id'],
            'candidate_group_ids' => [Rule::excludeIf($this->isSecondaryExamRequest() || $this->isProfessionalExamRequest()), 'nullable', 'array'],
            'candidate_group_ids.*' => [Rule::excludeIf($this->isSecondaryExamRequest() || $this->isProfessionalExamRequest()), 'string', 'exists:candidate_groups,id', 'distinct'],
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
            'academic_session_id' => [Rule::requiredIf($this->isSecondaryExamRequest()), Rule::prohibitedIf($this->isOrganizationExamRequest() || $this->isCbtCenterExamRequest()), 'nullable', 'exists:academic_sessions,id'],
            'term_id' => [Rule::requiredIf($this->isSecondaryExamRequest() && ! $this->filled('academic_term_id')), Rule::prohibitedIf($this->isOrganizationExamRequest() || $this->isCbtCenterExamRequest()), 'nullable', 'exists:academic_terms,id'],
            'academic_term_id' => [Rule::requiredIf($this->isSecondaryExamRequest() && ! $this->filled('term_id')), Rule::prohibitedIf($this->isOrganizationExamRequest() || $this->isCbtCenterExamRequest()), 'nullable', 'exists:academic_terms,id'],
            'school_class_id' => [Rule::prohibitedIf($this->isOrganizationExamRequest() || $this->isCbtCenterExamRequest()), 'nullable', 'exists:school_classes,id'],
            'student_group_id' => [Rule::requiredIf($this->isSecondaryExamRequest()), Rule::prohibitedIf($this->isOrganizationExamRequest() || $this->isProfessionalExamRequest() || $this->isCbtCenterExamRequest()), 'nullable', 'exists:student_groups,id'],
            'subject_id' => ['nullable', 'exists:subjects,id'],
            'programme_id' => [Rule::requiredIf($this->isProfessionalExamRequest()), Rule::prohibitedIf($this->isOrganizationExamRequest() || $this->isCbtCenterExamRequest()), 'nullable', 'exists:programmes,id'],
            'course_id' => [Rule::prohibitedIf($this->isOrganizationExamRequest() || $this->isCbtCenterExamRequest()), 'nullable', 'exists:courses,id'],
            'module_id' => [Rule::prohibitedIf($this->isOrganizationExamRequest() || $this->isCbtCenterExamRequest()), 'nullable', 'exists:modules,id'],
            'training_batch_id' => [Rule::requiredIf($this->isProfessionalExamRequest()), Rule::prohibitedIf($this->isOrganizationExamRequest() || $this->isCbtCenterExamRequest()), 'nullable', 'exists:training_batches,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'academic_session_id.required' => 'Choose the academic session for this terminal exam.',
            'term_id.required' => 'Choose the term for this terminal exam.',
            'academic_term_id.required' => 'Choose the term for this terminal exam.',
            'student_group_id.required' => 'Choose the student group that should write this exam.',
            'training_batch_id.required' => 'Choose the batch that should write this professional exam.',
            'subjects.required' => 'Add at least one subject setup for the exam paper.',
            'subjects.*.subject_id.required' => 'Choose a subject for each paper setup row.',
            'subjects.*.question_bank_id.required' => 'Choose a question bank for each paper setup row.',
            'subjects.*.number_of_questions.required' => 'Enter the number of questions for each subject.',
            'subjects.*.marks_per_question.required' => 'Enter the mark per question for each subject.',
            'exam_code.unique' => 'This exam code is already in use. Enter a different exam code.',
            'end_at.after' => 'The exam end time must be after the start time.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (($this->user()?->isTeacher() || $this->user()?->isFacilitator()) && $this->input('exam_category') !== Exam::CATEGORY_ASSESSMENT) {
                $validator->errors()->add('exam_category', 'This account can only create and update assessments.');
            }

            if ($this->isSecondaryExamRequest()) {
                if (! in_array($this->input('exam_category'), [Exam::CATEGORY_TERMINAL, Exam::CATEGORY_ASSESSMENT], true)) {
                    $validator->errors()->add('exam_category', 'Secondary school exams must be terminal exams or assessments.');
                }

                if (($this->input('exam_mode') ?? $this->input('mode')) !== Exam::MODE_TRADITIONAL) {
                    $validator->errors()->add('exam_mode', 'Secondary school exams must use traditional mode.');
                }

                foreach (['programme_id', 'course_id', 'module_id', 'training_batch_id'] as $field) {
                    if ($this->filled($field)) {
                        $validator->errors()->add($field, 'This field is not allowed for secondary school terminal exams.');
                    }
                }

                if ($this->filled('student_group_id')) {
                    $belongsToSchool = StudentGroup::query()
                        ->whereKey($this->input('student_group_id'))
                        ->whereHas('schoolClass', fn ($query) => $query
                            ->when($this->user()?->secondary_school_id, fn ($scope) => $scope->where('secondary_school_id', $this->user()->secondary_school_id))
                            ->when($this->user()?->school_id, fn ($scope) => $scope->where('school_id', $this->user()->school_id))
                            ->when($this->input('secondary_school_id'), fn ($scope) => $scope->where('secondary_school_id', $this->input('secondary_school_id'))))
                        ->exists();

                    if (! $belongsToSchool) {
                        $validator->errors()->add('student_group_id', 'Choose a student group that belongs to this secondary school.');
                    }
                }
            }

            foreach ($this->input('subjects', []) as $index => $subject) {
                $bankIds = collect($subject['question_bank_ids'] ?? [])
                    ->merge([$subject['question_bank_id'] ?? null])
                    ->filter()
                    ->unique()
                    ->values();

                if ($bankIds->isEmpty()) {
                    $validator->errors()->add("subjects.{$index}.question_bank_id", 'Choose at least one question bank for each paper setup row.');
                }
            }

            if ($this->isProfessionalExamRequest()) {
                if (! in_array($this->input('exam_category'), [Exam::CATEGORY_PROFESSIONAL, Exam::CATEGORY_CERTIFICATION, Exam::CATEGORY_PRACTICE, Exam::CATEGORY_ASSESSMENT], true)) {
                    $validator->errors()->add('exam_category', 'Professional school exams must be professional, certification, practice, or assessments.');
                }

                if (! in_array($this->input('exam_mode') ?? $this->input('mode'), [Exam::MODE_TRADITIONAL, Exam::MODE_ADAPTIVE], true)) {
                    $validator->errors()->add('exam_mode', 'Professional school exams must use traditional or adaptive mode.');
                }

                foreach (['academic_session_id', 'term_id', 'academic_term_id', 'school_class_id', 'student_group_id', 'subject_id'] as $field) {
                    if ($this->filled($field)) {
                        $validator->errors()->add($field, 'This field is not allowed for professional school exams.');
                    }
                }

                if ($this->filled('training_batch_id')) {
                    $belongsToSchool = TrainingBatch::query()
                        ->whereKey($this->input('training_batch_id'))
                        ->when($this->user()?->professional_school_id, fn ($query) => $query->where('professional_school_id', $this->user()->professional_school_id))
                        ->when($this->input('professional_school_id'), fn ($query) => $query->where('professional_school_id', $this->input('professional_school_id')))
                        ->exists();

                    if (! $belongsToSchool) {
                        $validator->errors()->add('training_batch_id', 'Choose a batch that belongs to this professional school.');
                    }
                }
            }

            if ($this->isCbtCenterExamRequest()) {
                if (! in_array($this->input('exam_category'), [Exam::CATEGORY_RECRUITMENT, Exam::CATEGORY_ASSESSMENT, Exam::CATEGORY_CERTIFICATION, Exam::CATEGORY_PROFESSIONAL, Exam::CATEGORY_PRACTICE, Exam::CATEGORY_GENERAL], true)) {
                    $validator->errors()->add('exam_category', 'CBT center exams must use recruitment, assessment, certification, professional, practice, or general category.');
                }

                if (! in_array($this->input('exam_mode') ?? $this->input('mode'), [Exam::MODE_TRADITIONAL, Exam::MODE_ADAPTIVE], true)) {
                    $validator->errors()->add('exam_mode', 'CBT center exams must use traditional or adaptive mode.');
                }

                if (! $this->hasCandidateGroups()) {
                    $validator->errors()->add('candidate_group_ids', 'Choose candidate groups for this CBT center exam.');
                }

                if (! empty($this->input('candidate_ids', []))) {
                    $validator->errors()->add('candidate_ids', 'CBT center exams must use candidate groups, not direct candidate selection.');
                }

                foreach (['academic_session_id', 'term_id', 'academic_term_id', 'school_class_id', 'student_group_id', 'programme_id', 'course_id', 'module_id', 'training_batch_id'] as $field) {
                    if ($this->filled($field)) {
                        $validator->errors()->add($field, 'This field is not allowed for CBT center exams.');
                    }
                }
            }

            if ($this->isOrganizationExamRequest()) {
                if (! $this->hasCandidateGroups() && empty($this->input('candidate_ids', []))) {
                    $validator->errors()->add('candidate_ids', 'Choose candidates for this organization exam.');
                }
            }
        });
    }

    private function isOrganizationExamRequest(): bool
    {
        if ($this->isSecondaryExamRequest() || $this->isProfessionalExamRequest() || $this->isCbtCenterExamRequest()) {
            return false;
        }

        return $this->user()?->organization_id !== null
            || $this->route('organization') !== null
            || $this->input('exam_owner_type') === Exam::OWNER_ORGANIZATION
            || filled($this->input('organization_id'));
    }

    private function hasCandidateGroups(): bool
    {
        return $this->filled('candidate_group_id') || count(array_filter($this->input('candidate_group_ids', []))) > 0;
    }

    private function isSecondaryExamRequest(): bool
    {
        return $this->user()?->secondary_school_id !== null
            || $this->user()?->school_id !== null
            || $this->route('secondarySchool') !== null
            || $this->input('exam_owner_type') === Exam::OWNER_SECONDARY_SCHOOL
            || filled($this->input('school_id'))
            || filled($this->input('secondary_school_id'));
    }

    private function isProfessionalExamRequest(): bool
    {
        return $this->user()?->professional_school_id !== null
            || $this->route('professionalSchool') !== null
            || $this->input('exam_owner_type') === Exam::OWNER_PROFESSIONAL_SCHOOL
            || filled($this->input('professional_school_id'));
    }

    private function isCbtCenterExamRequest(): bool
    {
        return $this->user()?->cbt_center_id !== null
            || $this->route('cbtCenter') !== null
            || $this->input('exam_owner_type') === Exam::OWNER_CBT_CENTER
            || filled($this->input('cbt_center_id'));
    }
}
