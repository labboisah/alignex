<?php

namespace App\Http\Requests;

use App\Models\Course;
use App\Models\Exam;
use App\Models\StudentGroup;
use App\Models\TrainingBatch;
use App\Services\CurrentContextService;
use App\Support\AdaptiveSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class StoreExamRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (($this->input('exam_mode') ?? $this->input('mode')) === Exam::MODE_ADAPTIVE && is_array($this->input('settings', []))) {
            $questions = collect(is_array($this->input('subjects')) ? $this->input('subjects') : [])
                ->sum(fn ($row) => is_array($row) && is_numeric($row['number_of_questions'] ?? null) ? (int) $row['number_of_questions'] : 0);
            $this->merge(['settings' => array_replace(AdaptiveSettings::defaults($questions), [
                'progressive_remediation_enabled' => true, 'recovery_penalty_percent' => 10, 'max_scored_levels' => 3,
                'min_level_budget' => '0.01', 'mastery_threshold_percent' => 70, 'min_evidence_per_area' => 1,
                'level_duration_minutes' => $this->input('duration_minutes', 30), 'progression_closes_at' => $this->input('end_at'),
                'level_cooldown_minutes' => 0, 'allow_unscored_remediation' => false, 'adaptive_show_level_feedback' => true,
            ], $this->input('settings', []))]);
        }
    }

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
            ...array_fill_keys(array_map(fn ($key) => 'settings.'.$key, array_keys(AdaptiveSettings::rules(false))), ['sometimes']),
            'settings.adaptive_show_level_feedback' => ['sometimes', 'boolean'],
            'adaptive_pilot' => ['exclude_unless:mode,adaptive', 'sometimes', 'array:online_enabled,offline_enabled,purpose,diagnostic_only'],
            'adaptive_pilot.online_enabled' => ['required_with:adaptive_pilot', 'boolean'],
            'adaptive_pilot.offline_enabled' => ['required_with:adaptive_pilot', 'boolean'],
            'adaptive_pilot.purpose' => [Rule::requiredIf(fn () => $this->boolean('adaptive_pilot.online_enabled') || $this->boolean('adaptive_pilot.offline_enabled')), 'nullable', 'string', 'min:10', 'max:2000'],
            'adaptive_pilot.diagnostic_only' => [Rule::when(fn () => $this->boolean('adaptive_pilot.online_enabled') || $this->boolean('adaptive_pilot.offline_enabled'), ['required', 'accepted'], ['sometimes', 'boolean'])],
            'subjects.*.topic_ids' => ['nullable', 'array'],
            'subjects.*.topic_ids.*' => ['string', 'exists:topics,id', 'distinct'],
            'subjects.*.module_id' => ['nullable', 'integer', 'exists:modules,id'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'institution_id' => ['nullable', 'integer', 'exists:institutions,id'],
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
            'subjects.*.subject_id' => [Rule::requiredIf(! $this->isInstitutionExamRequest()), 'nullable', 'string', 'exists:subjects,id'],
            'subjects.*.course_id' => [Rule::requiredIf($this->isInstitutionExamRequest()), 'nullable', 'integer', 'exists:courses,id'],
            'subjects.*.question_bank_id' => ['nullable', 'exists:question_banks,id'],
            'subjects.*.question_bank_ids' => ['nullable', 'array'],
            'subjects.*.question_bank_ids.*' => ['nullable', 'exists:question_banks,id'],
            'subjects.*.number_of_questions' => ['required', 'integer', 'min:1', 'max:1000'],
            'subjects.*.marks_per_question' => ['required', 'numeric', 'min:0.01', 'max:1000'],
            'subjects.*.duration_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'subjects.*.difficulty_distribution' => ['nullable', 'array:easy,medium,hard'],
            'subjects.*.difficulty_distribution.*' => ['required', 'integer', 'min:0', 'max:1000'],
            'question_bank_id' => ['nullable', 'exists:question_banks,id'],
            'candidate_ids' => [Rule::excludeIf($this->isSecondaryExamRequest() || $this->isProfessionalExamRequest() || $this->isInstitutionExamRequest()), 'nullable', 'array'],
            'candidate_ids.*' => [Rule::excludeIf($this->isSecondaryExamRequest() || $this->isProfessionalExamRequest() || $this->isInstitutionExamRequest()), 'string', 'exists:candidates,id', 'distinct'],
            'candidate_group_id' => [Rule::excludeIf($this->isSecondaryExamRequest() || $this->isProfessionalExamRequest()), 'nullable', 'string', 'max:100', 'exists:candidate_groups,id'],
            'candidate_group_ids' => [Rule::excludeIf($this->isSecondaryExamRequest() || $this->isProfessionalExamRequest()), 'nullable', 'array'],
            'candidate_group_ids.*' => [Rule::excludeIf($this->isSecondaryExamRequest() || $this->isProfessionalExamRequest()), 'string', 'exists:candidate_groups,id', 'distinct'],
            'settings.shuffle_questions' => ['required', 'boolean'],
            'settings.shuffle_options' => ['required', 'boolean'],
            'settings.show_result_immediately' => ['required', 'boolean'],
            'settings.allow_back_navigation' => ['required', 'boolean'],
            'settings.require_webcam' => ['required', 'boolean'],
            'settings.require_fullscreen' => ['required', 'boolean'],
            'settings.monitor_screenshots' => ['nullable', 'boolean'],
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
            $exam = $this->route('exam');
            if ($exam instanceof Exam && $exam->effectiveMode() === Exam::MODE_TRADITIONAL
                && $exam->attempts()->whereHas('papers')->exists()) {
                $configured = $exam->examSubjects->map(fn ($row) => [
                    (string) $row->subject_id, (int) $row->question_count, (float) $row->marks_per_question,
                ])->sortBy(fn ($row) => $row[0])->values()->all();
                $requested = collect($this->input('subjects', []))->filter(fn ($row) => is_array($row))->map(fn ($row) => [
                    (string) ($row['subject_id'] ?? ''), (int) ($row['number_of_questions'] ?? 0), (float) ($row['marks_per_question'] ?? 0),
                ])->sortBy(fn ($row) => $row[0])->values()->all();
                if ($configured !== $requested) {
                    $validator->errors()->add('subjects', 'Question counts and Marks Each cannot change after papers have been generated. Create a new exam to use a different scoring setup.');
                }
            }

            foreach ($this->input('subjects', []) as $index => $row) {
                $distribution = is_array($row) ? ($row['difficulty_distribution'] ?? []) : [];
                if (is_array($distribution) && $distribution !== [] && ! $validator->errors()->any()) {
                    if (array_sum($distribution) != (int) ($row['number_of_questions'] ?? 0)) {
                        $validator->errors()->add("subjects.{$index}.difficulty_distribution", 'Difficulty counts must add up to the number of questions.');
                    }
                    if (($this->input('exam_mode') ?? $this->input('mode')) === Exam::MODE_ADAPTIVE) {
                        $validator->errors()->add("subjects.{$index}.difficulty_distribution", 'Fixed difficulty selection requires traditional mode. Adaptive exams choose difficulty as candidates progress.');
                    }
                }
            }
            if (($this->input('exam_mode') ?? $this->input('mode')) !== Exam::MODE_ADAPTIVE) {
                $seenSubjects = [];
                foreach ($this->input('subjects', []) as $index => $row) {
                    $subjectId = is_array($row) ? ($row['subject_id'] ?? null) : null;
                    if (! is_string($subjectId) || $subjectId === '') {
                        continue;
                    }
                    if (isset($seenSubjects[$subjectId])) {
                        $validator->errors()->add("subjects.{$index}.subject_id", 'This subject is already included. Select its question banks in one paper row for a traditional exam.');
                    }
                    $seenSubjects[$subjectId] = true;
                }
            }

            if ($this->filled('exam_mode') && $this->input('mode') !== $this->input('exam_mode')) {
                $validator->errors()->add('exam_mode', 'Legacy mode and exam mode must agree.');
            }
            if (! $validator->errors()->any() && ($this->input('exam_mode') ?? $this->input('mode')) === Exam::MODE_ADAPTIVE) {
                try {
                    AdaptiveSettings::validate($this->input('settings', []), (int) collect($this->input('subjects', []))->sum('number_of_questions'), $this->input('start_at'));
                } catch (ValidationException $exception) {
                    foreach ($exception->errors() as $field => $messages) {
                        foreach ($messages as $message) {
                            $validator->errors()->add('settings.'.$field, $message);
                        }
                    }
                }
            } elseif (($this->input('exam_mode') ?? $this->input('mode')) !== Exam::MODE_ADAPTIVE && $this->boolean('settings.progressive_remediation_enabled')) {
                $validator->errors()->add('settings.progressive_remediation_enabled', 'Progressive recovery requires adaptive mode.');
            }
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
                    ->merge([$this->input('question_bank_id')])
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

            if ($this->isInstitutionExamRequest()) {
                if ($this->input('exam_category') !== Exam::CATEGORY_ASSESSMENT) {
                    $validator->errors()->add('exam_category', 'Institution exams must use assessment category.');
                }

                if (! $this->hasCandidateGroups()) {
                    $validator->errors()->add('candidate_group_ids', 'Choose candidate groups for this institution assessment.');
                }

                foreach (['academic_session_id', 'term_id', 'academic_term_id', 'school_class_id', 'student_group_id', 'subject_id', 'module_id', 'training_batch_id'] as $field) {
                    if ($this->filled($field)) {
                        $validator->errors()->add($field, 'This field is not allowed for institution assessments.');
                    }
                }

                $institutionId = $this->institutionId();
                foreach ($this->input('subjects', []) as $index => $subject) {
                    if (! filled($subject['course_id'] ?? null)) {
                        continue;
                    }

                    $belongsToInstitution = Course::query()
                        ->whereKey($subject['course_id'])
                        ->where('institution_id', $institutionId)
                        ->when($this->user()?->isFacilitator(), fn ($query) => $query->whereIn('id', $this->user()->assignedCourses()->select('courses.id')))
                        ->exists();

                    if (! $belongsToInstitution) {
                        $validator->errors()->add("subjects.{$index}.course_id", 'Choose an assigned course within this institution.');
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
        if ($this->isInstitutionExamRequest() || $this->isSecondaryExamRequest() || $this->isProfessionalExamRequest() || $this->isCbtCenterExamRequest()) {
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

    private function isInstitutionExamRequest(): bool
    {
        $context = app(CurrentContextService::class)->current($this->user());

        return ($context['type'] ?? null) === 'institution'
            || $this->user()?->institution_id !== null
            || $this->input('exam_owner_type') === Exam::OWNER_INSTITUTION
            || filled($this->input('institution_id'));
    }

    private function institutionId(): int|string|null
    {
        $context = app(CurrentContextService::class)->current($this->user());

        return $this->input('institution_id')
            ?: ((($context['type'] ?? null) === 'institution') ? $context['id'] : null)
            ?: $this->user()?->institution_id;
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
