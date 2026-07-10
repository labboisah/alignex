<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExamRequest;
use App\Http\Requests\UpdateExamRequest;
use App\Http\Resources\ExamResource;
use App\Http\Resources\SubjectResource;
use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use App\Models\Candidate;
use App\Models\CandidateGroup;
use App\Models\Center;
use App\Models\CbtCenter;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\Organization;
use App\Models\ProfessionalSchool;
use App\Models\QuestionBank;
use App\Models\School;
use App\Models\SecondarySchool;
use App\Models\StudentGroup;
use App\Models\Subject;
use App\Services\CurrentContextService;
use App\Services\ExamParticipantAssignmentService;
use App\Services\ExamStatusService;
use App\Support\ExamOwnershipRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ExamController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Exam::class);
        app(ExamStatusService::class)->syncOverdue();

        return Inertia::render('Exams/Index', [
            'exams' => ExamResource::collection(
                $this->scopedExams($request)
                    ->with(['organization', 'school', 'center', 'secondarySchool', 'professionalSchool', 'cbtCenter', 'examType', 'questionBank'])
                    ->withCount(['examSubjects', 'participants', 'attempts'])
                    ->latest()
                    ->get()
            ),
            'can' => ['create' => $request->user()->can('create', Exam::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Exam::class);

        return Inertia::render('Exams/Create', $this->formOptions($request));
    }

    public function store(StoreExamRequest $request): RedirectResponse
    {
        $exam = DB::transaction(fn () => $this->persistExam($request));

        return redirect()->route('exams.show', $exam)->with('success', 'Exam created.');
    }

    public function show(Request $request, Exam $exam): Response
    {
        Gate::authorize('view', $exam);
        $exam = app(ExamStatusService::class)->sync($exam);

        return Inertia::render('Exams/Show', [
            'exam' => ExamResource::make($exam->load(['organization', 'school', 'center', 'secondarySchool', 'professionalSchool', 'cbtCenter', 'examType', 'questionBank', 'examSubjects.subject', 'examSubjects.questionBank', 'candidates'])->loadCount(['participants', 'attempts', 'examSubjects'])),
            'can' => [
                'update' => $request->user()->can('update', $exam),
                'cancel' => $request->user()->can('update', $exam) && $exam->status !== Exam::STATUS_CANCELLED,
                'delete' => $request->user()->can('delete', $exam),
            ],
        ]);
    }

    public function edit(Request $request, Exam $exam): Response
    {
        Gate::authorize('update', $exam);
        $exam = app(ExamStatusService::class)->sync($exam);

        return Inertia::render('Exams/Edit', [
            'exam' => ExamResource::make($exam->load(['organization', 'school', 'center', 'secondarySchool', 'professionalSchool', 'cbtCenter', 'examType', 'questionBank', 'examSubjects.subject', 'examSubjects.questionBank', 'candidates'])->loadCount(['participants', 'attempts', 'examSubjects'])),
            ...$this->formOptions($request),
        ]);
    }

    public function update(UpdateExamRequest $request, Exam $exam): RedirectResponse
    {
        DB::transaction(fn () => $this->persistExam($request, $exam));

        return redirect()->route('exams.show', $exam)->with('success', 'Exam updated.');
    }

    public function cancel(Exam $exam): RedirectResponse
    {
        Gate::authorize('update', $exam);

        $exam->update(['status' => Exam::STATUS_CANCELLED]);

        return back()->with('success', 'Exam cancelled.');
    }

    public function destroy(Exam $exam): RedirectResponse
    {
        Gate::authorize('delete', $exam);
        abort_if($exam->attempts()->exists(), 422, 'Exams with candidate attempts cannot be deleted.');

        $exam->delete();

        return redirect()->route('exams.index')->with('success', 'Exam deleted.');
    }

    public function refreshParticipants(Request $request, Exam $exam): RedirectResponse
    {
        Gate::authorize('update', $exam);

        $summary = DB::transaction(fn () => $this->refreshExamParticipants($exam->fresh()));

        return back()->with('success', $summary);
    }

    private function refreshExamParticipants(Exam $exam): string
    {
        $ownerType = $exam->effectiveOwnerType();
        $settings = $exam->settings ?? [];
        $assignment = app(ExamParticipantAssignmentService::class);

        if ($ownerType === Exam::OWNER_SECONDARY_SCHOOL) {
            $groupId = data_get($settings, 'secondary_student_group_id');

            if (! $groupId) {
                throw ValidationException::withMessages(['student_group_id' => 'This exam has no student group to refresh from.']);
            }

            $studentIds = $this->secondaryStudentIdsForExam($this->tenantForExam($exam), ['student_group_id' => $groupId]);
            $exam->forceFill([
                'settings' => [
                    ...$settings,
                    'secondary_student_group_id' => $groupId,
                    'secondary_student_ids' => $studentIds,
                ],
            ])->save();
            $assignment->syncStudents($exam, $studentIds);

            return count($studentIds).' students refreshed from the selected group.';
        }

        if ($ownerType === Exam::OWNER_PROFESSIONAL_SCHOOL) {
            $batchId = data_get($settings, 'professional_training_batch_id') ?? $exam->training_batch_id;

            if (! $batchId) {
                $candidateIds = collect(data_get($settings, 'participant_candidate_ids', []))->filter()->map(fn ($id) => (string) $id)->unique()->values()->all();
                $assignment->syncCandidates($exam, $candidateIds);

                return count($candidateIds).' candidates refreshed from saved exam participants.';
            }

            $candidateIds = $this->professionalBatchCandidateIds($this->tenantForExam($exam), ['training_batch_id' => $batchId]);
            $exam->forceFill([
                'training_batch_id' => $batchId,
                'settings' => [
                    ...$settings,
                    'professional_training_batch_id' => $batchId,
                    'participant_candidate_ids' => $candidateIds,
                ],
            ])->save();
            $assignment->syncCandidates($exam, $candidateIds);

            return count($candidateIds).' candidates refreshed from the selected batch.';
        }

        if (in_array($ownerType, [Exam::OWNER_CBT_CENTER, Exam::OWNER_ORGANIZATION], true)) {
            $groupIds = collect(
                data_get($settings, $ownerType === Exam::OWNER_CBT_CENTER ? 'cbt_candidate_group_ids' : 'participant_candidate_group_ids', [])
            )
                ->merge([data_get($settings, $ownerType === Exam::OWNER_CBT_CENTER ? 'cbt_candidate_group_id' : 'participant_candidate_group_id')])
                ->filter()
                ->map(fn ($id) => (string) $id)
                ->unique()
                ->values()
                ->all();

            $candidateIds = $groupIds !== []
                ? $this->resolveCandidateIds($this->tenantForExam($exam), ['candidate_group_ids' => $groupIds])
                : collect(data_get($settings, 'participant_candidate_ids', data_get($settings, 'cbt_candidate_ids', [])))->filter()->map(fn ($id) => (string) $id)->unique()->values()->all();

            $nextSettings = [
                ...$settings,
                'participant_candidate_ids' => $candidateIds,
                'participant_candidate_group_id' => $groupIds[0] ?? data_get($settings, 'participant_candidate_group_id'),
                'participant_candidate_group_ids' => $groupIds,
            ];

            if ($ownerType === Exam::OWNER_CBT_CENTER) {
                $nextSettings = [
                    ...$nextSettings,
                    'cbt_candidate_ids' => $candidateIds,
                    'cbt_candidate_group_id' => $groupIds[0] ?? data_get($settings, 'cbt_candidate_group_id'),
                    'cbt_candidate_group_ids' => $groupIds,
                ];
            }

            $exam->forceFill(['settings' => $nextSettings])->save();
            $assignment->syncCandidates($exam, $candidateIds);

            return count($candidateIds).' candidates refreshed from '.($groupIds === [] ? 'saved exam participants.' : 'the selected group(s).');
        }

        throw ValidationException::withMessages(['exam' => 'This exam owner type does not support participant refresh.']);
    }

    private function persistExam(StoreExamRequest $request, ?Exam $exam = null): Exam
    {
        $data = $request->validated();
        $tenant = $this->tenantFor($request, $data, $exam);
        $examType = $this->examTypeFor($data['exam_type']);
        $examCategory = $data['exam_category'] ?? $this->categoryForLegacyType($data['exam_type'], $tenant['exam_owner_type']);
        $examMode = $data['exam_mode'] ?? $data['mode'];
        $totalMarks = collect($data['subjects'])->sum(fn (array $subject) => (float) $subject['number_of_questions'] * (float) $subject['marks_per_question']);

        if ((float) $data['pass_mark'] > $totalMarks) {
            throw ValidationException::withMessages(['pass_mark' => 'Pass mark cannot be greater than total marks.']);
        }

        if (! ExamOwnershipRules::isValid($tenant['exam_owner_type'], $examCategory, $examMode)) {
            throw ValidationException::withMessages([
                'exam_category' => 'This exam category and mode are not allowed for the selected owner.',
            ]);
        }

        $payload = [
            ...$tenant,
            'owner_type' => $tenant['exam_owner_type'],
            'owner_id' => $tenant['exam_owner_id'],
            'exam_owner_type' => $tenant['exam_owner_type'],
            'exam_owner_id' => $tenant['exam_owner_id'],
            'academic_session_id' => $data['academic_session_id'] ?? null,
            'academic_term_id' => $data['academic_term_id'] ?? $data['term_id'] ?? null,
            'school_class_id' => $data['school_class_id'] ?? null,
            'class_arm_id' => null,
            'subject_id' => $data['subject_id'] ?? (in_array($tenant['exam_owner_type'], [Exam::OWNER_PROFESSIONAL_SCHOOL, Exam::OWNER_CBT_CENTER], true) ? null : ($data['subjects'][0]['subject_id'] ?? null)),
            'question_bank_id' => $data['question_bank_id'] ?? $this->firstQuestionBankId($data['subjects'] ?? []) ?? null,
            'programme_id' => $data['programme_id'] ?? null,
            'course_id' => $data['course_id'] ?? null,
            'module_id' => $data['module_id'] ?? null,
            'training_batch_id' => $data['training_batch_id'] ?? null,
            'exam_type_id' => $examType->id,
            'created_by' => $exam?->created_by ?? $request->user()->id,
            'title' => $data['title'],
            'code' => strtoupper($data['exam_code']),
            'mode' => $data['mode'],
            'exam_mode' => $examMode,
            'exam_category' => $examCategory,
            'delivery_mode' => $data['delivery_mode'],
            'starts_at' => $data['start_at'],
            'ends_at' => $data['end_at'],
            'duration_minutes' => $data['duration_minutes'],
            'total_marks' => $totalMarks,
            'pass_mark' => $data['pass_mark'],
            'timezone' => config('app.timezone', 'UTC'),
            'status' => $data['status'],
            'settings' => $data['settings'],
        ];

        if ($tenant['exam_owner_type'] === Exam::OWNER_SECONDARY_SCHOOL) {
            $secondaryStudentIds = $this->secondaryStudentIdsForExam($tenant, $data);
            $payload['settings'] = [
                ...($payload['settings'] ?? []),
                'secondary_student_group_id' => $data['student_group_id'] ?? null,
                'secondary_student_ids' => $secondaryStudentIds,
            ];
        }

        if (in_array($tenant['exam_owner_type'], [Exam::OWNER_ORGANIZATION, Exam::OWNER_CBT_CENTER], true) && ! empty($data['question_bank_id'])) {
            $this->authorizeQuestionBank($tenant, $data['question_bank_id'] ?? null);
        }

        if ($tenant['exam_owner_type'] === Exam::OWNER_CBT_CENTER) {
            $candidateIds = $this->resolveCandidateIds($tenant, $data);
            $payload['settings'] = [
                ...($payload['settings'] ?? []),
                'cbt_question_bank_id' => $data['question_bank_id'] ?? $this->firstQuestionBankId($data['subjects'] ?? []) ?? null,
                'cbt_candidate_ids' => $candidateIds,
                'participant_candidate_ids' => $candidateIds,
                'cbt_candidate_group_id' => $this->candidateGroupIds($data)[0] ?? null,
                'cbt_candidate_group_ids' => $this->candidateGroupIds($data),
            ];
        }

        if ($tenant['exam_owner_type'] === Exam::OWNER_PROFESSIONAL_SCHOOL) {
            $batchCandidateIds = $this->professionalBatchCandidateIds($tenant, $data);
            $payload['settings'] = [
                ...($payload['settings'] ?? []),
                'professional_training_batch_id' => $data['training_batch_id'] ?? null,
                'participant_candidate_ids' => $batchCandidateIds,
            ];
        }

        if ($tenant['exam_owner_type'] === Exam::OWNER_ORGANIZATION && array_key_exists('candidate_ids', $data)) {
            $candidateIds = $this->resolveCandidateIds($tenant, $data);
            $payload['settings'] = [
                ...($payload['settings'] ?? []),
                'participant_candidate_ids' => $candidateIds,
                'participant_candidate_group_id' => $this->candidateGroupIds($data)[0] ?? null,
                'participant_candidate_group_ids' => $this->candidateGroupIds($data),
            ];
        }

        $exam ? $exam->update($payload) : $exam = Exam::create($payload);
        $exam->examSubjects()->delete();

        foreach (array_values($data['subjects']) as $index => $subject) {
            $this->authorizeSubject($request, $subject['subject_id']);
            $bankIds = $this->resolveQuestionBankIds($subject, $data);
            $this->authorizeSubjectQuestionBanks($tenant, $bankIds, $subject['subject_id'], true);
            $exam->examSubjects()->create([
                'subject_id' => $subject['subject_id'],
                'question_bank_id' => $bankIds[0] ?? null,
                'display_order' => $index + 1,
                'question_count' => $subject['number_of_questions'],
                'marks_per_question' => $subject['marks_per_question'],
                'total_marks' => (float) $subject['number_of_questions'] * (float) $subject['marks_per_question'],
                'duration_minutes' => $subject['duration_minutes'] ?? null,
                'difficulty_distribution' => $subject['difficulty_distribution'] ?? null,
                'selection_rules' => ['question_bank_ids' => $bankIds],
            ]);
        }

        if ($tenant['exam_owner_type'] === Exam::OWNER_CBT_CENTER && array_key_exists('candidate_ids', $data)) {
            $this->syncCbtCandidates($exam, $tenant, $this->resolveCandidateIds($tenant, $data));
        }

        if ($tenant['exam_owner_type'] === Exam::OWNER_PROFESSIONAL_SCHOOL) {
            app(ExamParticipantAssignmentService::class)->syncCandidates($exam, data_get($exam->settings ?? [], 'participant_candidate_ids', []));
        }

        if ($tenant['exam_owner_type'] === Exam::OWNER_ORGANIZATION && array_key_exists('candidate_ids', $data)) {
            app(ExamParticipantAssignmentService::class)->syncCandidates($exam, $this->resolveCandidateIds($tenant, $data));
        }

        if ($tenant['exam_owner_type'] === Exam::OWNER_SECONDARY_SCHOOL) {
            app(ExamParticipantAssignmentService::class)->syncStudents($exam, data_get($exam->settings ?? [], 'secondary_student_ids', []));
        }

        return $exam;
    }

    private function secondaryStudentIdsForExam(array $tenant, array $data): array
    {
        if (! empty($data['student_group_id'])) {
            $group = StudentGroup::query()
                ->whereKey($data['student_group_id'])
                ->whereHas('schoolClass', fn ($query) => $query
                    ->when($tenant['secondary_school_id'] ?? null, fn ($scope) => $scope->where('secondary_school_id', $tenant['secondary_school_id']))
                    ->when($tenant['school_id'] ?? null, fn ($scope) => $scope->where('school_id', $tenant['school_id'])))
                ->firstOrFail();

            return $group->students()
                ->pluck('students.id')
                ->map(fn ($id) => (string) $id)
                ->all();
        }

        return [];
    }

    private function professionalBatchCandidateIds(array $tenant, array $data): array
    {
        if (empty($data['training_batch_id'])) {
            return [];
        }

        $query = Candidate::query()
            ->where('training_batch_id', $data['training_batch_id'])
            ->where('status', Candidate::STATUS_ACTIVE);

        if ($tenant['professional_school_id'] ?? null) {
            $query->where('professional_school_id', $tenant['professional_school_id']);
        }

        return $query
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    private function scopedExams(Request $request)
    {
        $user = $request->user();
        $organization = $request->route('organization');
        $secondarySchool = $request->route('secondarySchool');
        $professionalSchool = $request->route('professionalSchool');
        $cbtCenter = $request->route('cbtCenter');

        return Exam::query()
            ->when($organization, fn ($query) => $query->where('organization_id', $organization->id))
            ->when($secondarySchool, fn ($query) => $query->where('secondary_school_id', $secondarySchool->id))
            ->when($professionalSchool, fn ($query) => $query->where('professional_school_id', $professionalSchool->id))
            ->when($cbtCenter, fn ($query) => $query->where('cbt_center_id', $cbtCenter->id))
            ->when($request->filled('category'), fn ($query) => $query->where('exam_category', $request->query('category')))
            ->when($request->filled('mode'), fn ($query) => $query->where(fn ($inner) => $inner->where('exam_mode', $request->query('mode'))->orWhere('mode', $request->query('mode'))))
            ->when($user->isTeacher(), fn ($query) => $query->whereHas('examSubjects', fn ($subjectQuery) => $subjectQuery->whereIn('subject_id', $user->assignedSubjects()->select('subjects.id'))))
            ->when(! $user->isSuperAdmin() && $user->organization_id, fn ($query) => $query->where('organization_id', $user->organization_id))
            ->when(! $user->isSuperAdmin() && $user->school_id, fn ($query) => $query->where('school_id', $user->school_id))
            ->when(! $user->isSuperAdmin() && $user->center_id, fn ($query) => $query->where('center_id', $user->center_id))
            ->when(! $user->isSuperAdmin() && $user->secondary_school_id, fn ($query) => $query->where('secondary_school_id', $user->secondary_school_id))
            ->when(! $user->isSuperAdmin() && $user->professional_school_id, fn ($query) => $query->where('professional_school_id', $user->professional_school_id))
            ->when(! $user->isSuperAdmin() && $user->cbt_center_id, fn ($query) => $query->where('cbt_center_id', $user->cbt_center_id));
    }

    private function scopedSubjects(Request $request)
    {
        $user = $request->user();
        $organization = $request->route('organization');
        $secondarySchool = $request->route('secondarySchool');
        $professionalSchool = $request->route('professionalSchool');
        $cbtCenter = $request->route('cbtCenter');

        return Subject::query()
            ->when($organization, fn ($query) => $query->where('organization_id', $organization->id))
            ->when($secondarySchool, fn ($query) => $query->where('secondary_school_id', $secondarySchool->id))
            ->when($professionalSchool, fn ($query) => $query->where('professional_school_id', $professionalSchool->id))
            ->when($cbtCenter, fn ($query) => $query->where('cbt_center_id', $cbtCenter->id))
            ->when($user->isTeacher(), fn ($query) => $query->whereIn('id', $user->assignedSubjects()->select('subjects.id')))
            ->when(! $user->isSuperAdmin() && $user->organization_id, fn ($query) => $query->where('organization_id', $user->organization_id))
            ->when(! $user->isSuperAdmin() && $user->school_id, fn ($query) => $query->where('school_id', $user->school_id))
            ->when(! $user->isSuperAdmin() && $user->center_id, fn ($query) => $query->where('center_id', $user->center_id))
            ->when(! $user->isSuperAdmin() && $user->secondary_school_id, fn ($query) => $query->where('secondary_school_id', $user->secondary_school_id))
            ->when(! $user->isSuperAdmin() && $user->professional_school_id, fn ($query) => $query->where('professional_school_id', $user->professional_school_id))
            ->when(! $user->isSuperAdmin() && $user->cbt_center_id, fn ($query) => $query->where('cbt_center_id', $user->cbt_center_id));
    }

    private function authorizeSubject(Request $request, string $subjectId): void
    {
        if (! $this->scopedSubjects($request)->whereKey($subjectId)->exists()) {
            throw ValidationException::withMessages(['subjects' => 'Choose subjects within your allowed scope.']);
        }
    }

    private function tenantFor(Request $request, array $data, ?Exam $exam = null): array
    {
        $user = $request->user();

        if ($exam) {
            return [
                'organization_id' => $exam->organization_id,
                'school_id' => $exam->school_id,
                'center_id' => $exam->center_id,
                'secondary_school_id' => $exam->secondary_school_id,
                'professional_school_id' => $exam->professional_school_id,
                'cbt_center_id' => $exam->cbt_center_id,
                'exam_owner_type' => $exam->exam_owner_type ?? $exam->owner_type ?? $exam->effectiveOwnerType(),
                'exam_owner_id' => $exam->exam_owner_id ?? $exam->owner_id ?? $this->ownerIdFor($exam->effectiveOwnerType(), [
                    'organization_id' => $exam->organization_id,
                    'school_id' => $exam->school_id,
                    'center_id' => $exam->center_id,
                    'secondary_school_id' => $exam->secondary_school_id,
                    'professional_school_id' => $exam->professional_school_id,
                    'cbt_center_id' => $exam->cbt_center_id,
                ]),
            ];
        }

        if ($request->route('organization') && $user->isSuperAdmin()) {
            return $this->tenantPayload(Exam::OWNER_ORGANIZATION, $request->route('organization')->id);
        }

        if ($request->route('secondarySchool') && ($user->isSuperAdmin() || $user->canAccessSecondarySchool($request->route('secondarySchool')->id))) {
            return $this->tenantPayload(Exam::OWNER_SECONDARY_SCHOOL, $request->route('secondarySchool')->id);
        }

        if ($request->route('professionalSchool') && ($user->isSuperAdmin() || $user->canAccessProfessionalSchool($request->route('professionalSchool')->id))) {
            return $this->tenantPayload(Exam::OWNER_PROFESSIONAL_SCHOOL, $request->route('professionalSchool')->id);
        }

        if ($request->route('cbtCenter') && ($user->isSuperAdmin() || $user->canAccessCbtCenter($request->route('cbtCenter')->id) || $user->canAccessOrganization($request->route('cbtCenter')->organization_id))) {
            return $this->tenantPayload(Exam::OWNER_CBT_CENTER, $request->route('cbtCenter')->id);
        }

        if (filled($data['secondary_school_id'] ?? null) && ($user->isSuperAdmin() || SecondarySchool::query()
            ->whereKey($data['secondary_school_id'])
            ->when($user->organization_id, fn ($query) => $query->where('organization_id', $user->organization_id))
            ->exists())) {
            return $this->tenantPayload(Exam::OWNER_SECONDARY_SCHOOL, $data['secondary_school_id']);
        }

        if (filled($data['professional_school_id'] ?? null) && ($user->isSuperAdmin() || ProfessionalSchool::query()
            ->whereKey($data['professional_school_id'])
            ->when($user->organization_id, fn ($query) => $query->where('organization_id', $user->organization_id))
            ->exists())) {
            return $this->tenantPayload(Exam::OWNER_PROFESSIONAL_SCHOOL, $data['professional_school_id']);
        }

        if (filled($data['cbt_center_id'] ?? null) && ($user->isSuperAdmin() || CbtCenter::query()
            ->whereKey($data['cbt_center_id'])
            ->when($user->organization_id, fn ($query) => $query->where('organization_id', $user->organization_id))
            ->exists())) {
            return $this->tenantPayload(Exam::OWNER_CBT_CENTER, $data['cbt_center_id']);
        }

        if ($user->isProfessionalSchoolAdmin() || ($user->isExaminer() && $user->professional_school_id)) {
            return $this->tenantPayload(Exam::OWNER_PROFESSIONAL_SCHOOL, $user->professional_school_id);
        }

        if ($user->isOrganizationAdmin() || ($user->isExaminer() && $user->organization_id)) {
            return $this->tenantPayload(Exam::OWNER_ORGANIZATION, $user->organization_id);
        }

        if ($user->isSecondarySchoolAdmin() || (($user->isExaminer() || $user->isTeacher()) && ($user->secondary_school_id || $user->school_id))) {
            return $user->secondary_school_id
                ? $this->tenantPayload(Exam::OWNER_SECONDARY_SCHOOL, $user->secondary_school_id)
                : $this->tenantPayload(Exam::OWNER_SECONDARY_SCHOOL, $user->school_id, ['school_id' => $user->school_id]);
        }

        if ($user->isCbtCenterAdmin() || ($user->isExaminer() && ($user->cbt_center_id || $user->center_id))) {
            return $user->cbt_center_id
                ? $this->tenantPayload(Exam::OWNER_CBT_CENTER, $user->cbt_center_id)
                : $this->tenantPayload(Exam::OWNER_CBT_CENTER, $user->center_id, ['center_id' => $user->center_id]);
        }

        $tenant = [
            'organization_id' => $data['organization_id'] ?: null,
            'school_id' => $data['school_id'] ?: null,
            'center_id' => $data['center_id'] ?: null,
            'secondary_school_id' => $data['secondary_school_id'] ?? null,
            'professional_school_id' => $data['professional_school_id'] ?? null,
            'cbt_center_id' => $data['cbt_center_id'] ?? null,
        ];
        $ownerType = $data['exam_owner_type'] ?? $this->ownerTypeFor($tenant);
        $ownerId = $data['exam_owner_id'] ?? $this->ownerIdFor($ownerType, $tenant);

        if ($user->isSuperAdmin() && $ownerType && $ownerId && collect($tenant)->filter(fn ($value) => filled($value))->count() === 1) {
            return $this->tenantPayload($ownerType, $ownerId, $tenant);
        }

        throw ValidationException::withMessages(['organization_id' => 'Choose exactly one organization, secondary school, professional school, or CBT center scope.']);
    }

    private function tenantForExam(Exam $exam): array
    {
        return [
            'organization_id' => $exam->organization_id,
            'school_id' => $exam->school_id,
            'center_id' => $exam->center_id,
            'secondary_school_id' => $exam->secondary_school_id,
            'professional_school_id' => $exam->professional_school_id,
            'cbt_center_id' => $exam->cbt_center_id,
            'exam_owner_type' => $exam->exam_owner_type ?? $exam->owner_type ?? $exam->effectiveOwnerType(),
            'exam_owner_id' => $exam->exam_owner_id ?? $exam->owner_id ?? $this->ownerIdFor($exam->effectiveOwnerType(), [
                'organization_id' => $exam->organization_id,
                'school_id' => $exam->school_id,
                'center_id' => $exam->center_id,
                'secondary_school_id' => $exam->secondary_school_id,
                'professional_school_id' => $exam->professional_school_id,
                'cbt_center_id' => $exam->cbt_center_id,
            ]),
        ];
    }

    private function examTypeFor(string $code): ExamType
    {
        return ExamType::query()->firstOrCreate(
            ['code' => $code],
            ['name' => str($code)->replace('_', ' ')->title()->toString(), 'status' => ExamType::STATUS_ACTIVE]
        );
    }

    private function formOptions(Request $request): array
    {
        return [
            'subjects' => SubjectResource::collection($this->scopedSubjects($request)->orderBy('name')->get()),
            'organizations' => $request->user()->isSuperAdmin() ? Organization::query()->orderBy('name')->get(['id', 'name', 'code']) : [],
            'schools' => $request->user()->isSuperAdmin() ? School::query()->orderBy('name')->get(['id', 'name', 'code']) : [],
            'centers' => $request->user()->isSuperAdmin() ? Center::query()->orderBy('name')->get(['id', 'name', 'code']) : [],
            'secondarySchools' => $request->user()->isSuperAdmin() ? SecondarySchool::query()->orderBy('name')->get(['id', 'name', 'code']) : [],
            'academicSessions' => $this->secondaryOptions($request, AcademicSession::query(), ['id', 'name', 'code', 'is_active']),
            'academicTerms' => $this->secondaryAcademicTerms($request),
            'professionalSchools' => $request->user()->isSuperAdmin() ? ProfessionalSchool::query()->orderBy('name')->get(['id', 'name', 'code']) : [],
            'cbtCenters' => $request->user()->isSuperAdmin() ? CbtCenter::query()->orderBy('name')->get(['id', 'name', 'code']) : [],
            'participantCandidates' => $this->participantCandidates($request),
            'cbtCandidates' => $this->cbtCandidates($request),
            'candidateGroups' => $this->candidateGroupOptions($request),
            'questionBanks' => $this->questionBankOptions($request),
            'programmes' => $this->professionalOptions($request, 'programmes'),
            'courses' => $this->professionalOptions($request, 'courses', ['id', 'programme_id', 'name', 'code']),
            'modules' => $this->professionalOptions($request, 'modules', ['id', 'programme_id', 'course_id', 'name', 'code']),
            'trainingBatches' => $this->professionalOptions($request, 'trainingBatches', ['id', 'programme_id', 'name', 'code']),
            'studentGroups' => $this->secondaryStudentGroups($request),
            'examTypes' => [
                ['value' => 'secondary', 'label' => 'Secondary'],
                ['value' => 'professional', 'label' => 'Professional'],
                ['value' => 'recruitment', 'label' => 'Recruitment'],
                ['value' => 'assessment', 'label' => 'Assessment'],
                ['value' => 'certification', 'label' => 'Certification'],
                ['value' => 'practice', 'label' => 'Practice'],
                ['value' => 'general', 'label' => 'General'],
            ],
            'examCategories' => [
                ['value' => Exam::CATEGORY_TERMINAL, 'label' => 'Terminal'],
                ['value' => Exam::CATEGORY_RECRUITMENT, 'label' => 'Recruitment'],
                ['value' => Exam::CATEGORY_ASSESSMENT, 'label' => 'Assessment'],
                ['value' => Exam::CATEGORY_CERTIFICATION, 'label' => 'Certification'],
                ['value' => Exam::CATEGORY_PROFESSIONAL, 'label' => 'Professional'],
                ['value' => Exam::CATEGORY_PRACTICE, 'label' => 'Practice'],
                ['value' => Exam::CATEGORY_GENERAL, 'label' => 'General'],
            ],
            'modes' => [
                ['value' => 'traditional', 'label' => 'Traditional'],
                ['value' => 'adaptive', 'label' => 'Adaptive'],
            ],
            'deliveryModes' => [
                ['value' => 'online', 'label' => 'Online'],
                ['value' => 'offline', 'label' => 'Offline'],
                ['value' => 'hybrid', 'label' => 'Hybrid'],
            ],
            'statuses' => [
                ['value' => Exam::STATUS_DRAFT, 'label' => 'Draft'],
                ['value' => Exam::STATUS_SCHEDULED, 'label' => 'Scheduled'],
                ['value' => Exam::STATUS_ACTIVE, 'label' => 'Active'],
                ['value' => Exam::STATUS_COMPLETED, 'label' => 'Completed'],
                ['value' => Exam::STATUS_CANCELLED, 'label' => 'Cancelled'],
            ],
        ];
    }

    private function categoryForLegacyType(string $examType, ?string $ownerType = null): string
    {
        return match ($examType) {
            'secondary' => $ownerType === Exam::OWNER_SECONDARY_SCHOOL ? Exam::CATEGORY_TERMINAL : Exam::CATEGORY_GENERAL,
            'professional' => Exam::CATEGORY_PROFESSIONAL,
            'recruitment' => Exam::CATEGORY_RECRUITMENT,
            'assessment' => Exam::CATEGORY_ASSESSMENT,
            'certification' => Exam::CATEGORY_CERTIFICATION,
            'practice' => Exam::CATEGORY_PRACTICE,
            'general' => Exam::CATEGORY_GENERAL,
            default => Exam::CATEGORY_GENERAL,
        };
    }

    private function professionalOptions(Request $request, string $relation, array $columns = ['id', 'name', 'code'])
    {
        $schoolId = $request->route('professionalSchool')?->id ?? $request->user()->professional_school_id;

        if (! $schoolId) {
            return [];
        }

        $school = ProfessionalSchool::query()->find($schoolId);

        return $school ? $school->{$relation}()->orderBy('name')->get($columns) : [];
    }

    private function secondaryOptions(Request $request, $query, array $columns)
    {
        $schoolId = $request->route('secondarySchool')?->id ?? $request->user()->secondary_school_id ?? $request->user()->school_id;

        if (! $schoolId && ! $request->user()->isSuperAdmin()) {
            return [];
        }

        $table = $query->getModel()->getTable();

        return $query
            ->when($schoolId, fn ($scope) => $scope->where(function ($inner) use ($schoolId, $table): void {
                $inner->whereRaw('1 = 0')
                    ->when(Schema::hasColumn($table, 'secondary_school_id'), fn ($item) => $item->orWhere('secondary_school_id', $schoolId))
                    ->when(Schema::hasColumn($table, 'school_id'), fn ($item) => $item->orWhere('school_id', $schoolId));
            }))
            ->orderBy('name')
            ->get($columns);
    }

    private function secondaryAcademicTerms(Request $request)
    {
        $schoolId = $request->route('secondarySchool')?->id ?? $request->user()->secondary_school_id ?? $request->user()->school_id;

        if (! $schoolId && ! $request->user()->isSuperAdmin()) {
            return [];
        }

        return AcademicTerm::query()
            ->when($schoolId, fn ($query) => $query->where(function ($inner) use ($schoolId): void {
                $inner->whereRaw('1 = 0')
                    ->when(Schema::hasColumn('academic_terms', 'secondary_school_id'), fn ($item) => $item->orWhere('secondary_school_id', $schoolId))
                    ->orWhereHas('session', fn ($session) => $session
                        ->where('secondary_school_id', $schoolId)
                        ->orWhere('school_id', $schoolId));
            }))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(['id', 'academic_session_id', 'name', 'code', 'is_active']);
    }

    private function secondaryStudentGroups(Request $request)
    {
        $schoolId = $request->route('secondarySchool')?->id ?? $request->user()->secondary_school_id ?? $request->user()->school_id;

        if (! $schoolId) {
            return [];
        }

        return StudentGroup::query()
            ->whereHas('schoolClass', fn ($query) => $query
                ->where('secondary_school_id', $schoolId)
                ->orWhere('school_id', $schoolId))
            ->with('schoolClass:id,name')
            ->orderBy('name')
            ->get(['id', 'school_class_id', 'name', 'code']);
    }

    private function cbtCandidates(Request $request)
    {
        $centerId = $request->route('cbtCenter')?->id ?? $request->user()->cbt_center_id ?? $request->user()->center_id;

        if (! $centerId) {
            return [];
        }

        return Candidate::query()
            ->where('cbt_center_id', $centerId)
            ->orderBy('candidate_number')
            ->get(['id', 'candidate_number', 'first_name', 'last_name'])
            ->map(fn (Candidate $candidate) => [
                'id' => $candidate->id,
                'name' => trim($candidate->first_name.' '.$candidate->last_name),
                'code' => $candidate->candidate_number,
            ]);
    }

    private function cbtQuestionBanks(Request $request)
    {
        $centerId = $request->route('cbtCenter')?->id ?? $request->user()->cbt_center_id ?? $request->user()->center_id;

        if (! $centerId) {
            return [];
        }

        return QuestionBank::query()
            ->where('cbt_center_id', $centerId)
            ->where('status', QuestionBank::STATUS_ACTIVE)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    private function participantCandidates(Request $request)
    {
        $user = $request->user();

        return Candidate::query()
            ->when(! $user->isSuperAdmin(), function ($query) use ($user): void {
                $query->where(function ($inner) use ($user): void {
                    $inner->whereRaw('1 = 0')
                        ->when($user->organization_id, fn ($scope) => $scope->orWhere('organization_id', $user->organization_id))
                        ->when($user->professional_school_id, fn ($scope) => $scope->orWhere('professional_school_id', $user->professional_school_id))
                        ->when($user->cbt_center_id, fn ($scope) => $scope->orWhere('cbt_center_id', $user->cbt_center_id));
                });
            })
            ->orderBy('candidate_number')
            ->limit(500)
            ->get(['id', 'candidate_number', 'first_name', 'last_name'])
            ->map(fn (Candidate $candidate) => [
                'id' => $candidate->id,
                'name' => trim($candidate->first_name.' '.$candidate->last_name),
                'code' => $candidate->candidate_number,
            ]);
    }

    private function candidateGroupOptions(Request $request)
    {
        $user = $request->user();
        $context = app(CurrentContextService::class)->current($user);
        $centerId = $request->route('cbtCenter')?->id
            ?? (($context['type'] ?? null) === 'cbt_center' ? $context['id'] : null)
            ?? $user->cbt_center_id
            ?? $user->center_id;

        return CandidateGroup::query()
            ->when(! $user->isSuperAdmin() && ! $centerId, function ($query) use ($user): void {
                $query->where(function ($inner) use ($user): void {
                    $inner->whereRaw('1 = 0')
                        ->when($user->organization_id, fn ($scope) => $scope->orWhere('organization_id', $user->organization_id));
                });
            })
            ->when(Schema::hasColumn('candidate_groups', 'cbt_center_id'), function ($query) use ($centerId): void {
                $centerId
                    ? $query->where('cbt_center_id', $centerId)
                    : $query->whereNull('cbt_center_id');
            })
            ->where('status', CandidateGroup::STATUS_ACTIVE)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    private function questionBankOptions(Request $request)
    {
        $user = $request->user();

        return QuestionBank::query()
            ->when($user->isTeacher(), fn ($query) => $query->whereIn('subject_id', $user->assignedSubjects()->select('subjects.id')))
            ->when(! $user->isSuperAdmin(), function ($query) use ($user): void {
                $query->where(function ($inner) use ($user): void {
                    $inner->whereRaw('1 = 0')
                        ->when($user->organization_id, fn ($scope) => $scope->orWhere('organization_id', $user->organization_id))
                        ->when($user->secondary_school_id, fn ($scope) => $scope->orWhere('secondary_school_id', $user->secondary_school_id))
                        ->when($user->school_id, fn ($scope) => $scope->orWhere('school_id', $user->school_id))
                        ->when($user->professional_school_id, fn ($scope) => $scope->orWhere('professional_school_id', $user->professional_school_id))
                        ->when($user->cbt_center_id, fn ($scope) => $scope->orWhere('cbt_center_id', $user->cbt_center_id));
                });
            })
            ->where('status', QuestionBank::STATUS_ACTIVE)
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'code', 'subject_id', 'course_id', 'module_id']);
    }

    private function authorizeQuestionBank(array $tenant, ?string $questionBankId): void
    {
        $ownerType = $tenant['exam_owner_type'] ?? null;
        $query = QuestionBank::query()->whereKey($questionBankId);

        match ($ownerType) {
            Exam::OWNER_ORGANIZATION => $query->where('organization_id', $tenant['organization_id'] ?? null),
            Exam::OWNER_CBT_CENTER => $query->where('cbt_center_id', $tenant['cbt_center_id'] ?? $tenant['center_id'] ?? null),
            default => null,
        };

        if (! $questionBankId || ! $query->exists()) {
            $label = $ownerType === Exam::OWNER_CBT_CENTER ? 'this CBT center' : 'this organization';

            throw ValidationException::withMessages(['question_bank_id' => "Choose a question bank within {$label}."]);
        }
    }

    private function authorizeSubjectQuestionBanks(array $tenant, array $questionBankIds, string $subjectId, bool $required = false): void
    {
        $normalized = collect($questionBankIds)
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        if ($normalized->isEmpty()) {
            if ($required) {
                throw ValidationException::withMessages(['subjects' => 'Choose a question bank for each secondary school subject row.']);
            }

            return;
        }

        foreach ($normalized as $questionBankId) {
            $ownerType = $tenant['exam_owner_type'] ?? null;
            $query = QuestionBank::query()
                ->whereKey($questionBankId)
                ->where('subject_id', $subjectId);

            match ($ownerType) {
                Exam::OWNER_ORGANIZATION => $query->where('organization_id', $tenant['organization_id'] ?? null),
                Exam::OWNER_SECONDARY_SCHOOL => $query
                    ->when($tenant['secondary_school_id'] ?? null, fn ($scope) => $scope->where('secondary_school_id', $tenant['secondary_school_id']))
                    ->when($tenant['school_id'] ?? null, fn ($scope) => $scope->where('school_id', $tenant['school_id'])),
                Exam::OWNER_PROFESSIONAL_SCHOOL => $query->where('professional_school_id', $tenant['professional_school_id'] ?? null),
                Exam::OWNER_CBT_CENTER => $query->where('cbt_center_id', $tenant['cbt_center_id'] ?? $tenant['center_id'] ?? null),
                default => null,
            };

            if (! $query->exists()) {
                throw ValidationException::withMessages(['subjects' => 'Choose question banks that belong to the selected subject and exam context.']);
            }
        }
    }

    private function resolveQuestionBankIds(array $subject, array $data): array
    {
        $raw = data_get($subject, 'question_bank_ids') ?? data_get($subject, 'question_bank_id') ?? data_get($data, 'question_bank_id');

        if (is_array($raw)) {
            return collect($raw)->filter()->map(fn ($id) => (string) $id)->unique()->values()->all();
        }

        if (is_string($raw) && $raw !== '') {
            return collect(explode(',', $raw))
                ->map(fn ($id) => trim($id))
                ->filter()
                ->map(fn ($id) => (string) $id)
                ->unique()
                ->values()
                ->all();
        }

        return [];
    }

    private function firstQuestionBankId(array $subjects): ?string
    {
        foreach ($subjects as $subject) {
            $ids = $this->resolveQuestionBankIds($subject, []);
            if ($ids !== []) {
                return $ids[0];
            }
        }

        return null;
    }

    private function resolveCandidateIds(array $tenant, array $data): array
    {
        $candidateIds = collect($data['candidate_ids'] ?? [])
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        $groupIds = $this->candidateGroupIds($data);

        if ($groupIds !== []) {
            $groups = CandidateGroup::query()
                ->whereIn('id', $groupIds)
                ->when($tenant['organization_id'] ?? null, fn ($query) => $query->where('organization_id', $tenant['organization_id']))
                ->when(Schema::hasColumn('candidate_groups', 'cbt_center_id') && ($tenant['cbt_center_id'] ?? null), fn ($query) => $query->where('cbt_center_id', $tenant['cbt_center_id']))
                ->with('candidates:id')
                ->get();

            if ($groups->count() !== count($groupIds)) {
                throw ValidationException::withMessages(['candidate_group_ids' => 'Choose candidate groups within this exam context.']);
            }

            return $groups->flatMap(fn (CandidateGroup $group) => $group->candidates->pluck('id'))
                ->map(fn ($id) => (string) $id)
                ->unique()
                ->values()
                ->all();
        }

        return $candidateIds->all();
    }

    private function candidateGroupIds(array $data): array
    {
        return collect($data['candidate_group_ids'] ?? [])
            ->merge([$data['candidate_group_id'] ?? null])
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $candidateIds
     */
    private function syncCbtCandidates(Exam $exam, array $tenant, array $candidateIds): void
    {
        $centerId = $tenant['cbt_center_id'] ?? $tenant['center_id'] ?? null;
        $allowed = Candidate::query()
            ->where('cbt_center_id', $centerId)
            ->whereIn('id', $candidateIds)
            ->pluck('id')
            ->all();

        if (count($allowed) !== count(array_unique($candidateIds))) {
            throw ValidationException::withMessages(['candidate_ids' => 'Choose candidates within this CBT center.']);
        }

        $exam->candidates()->sync(
            collect($allowed)->mapWithKeys(fn (string $id) => [$id => ['status' => 'assigned']])->all()
        );
    }

    private function ownerTypeFor(array $tenant): ?string
    {
        return match (true) {
            filled($tenant['organization_id'] ?? null) => Exam::OWNER_ORGANIZATION,
            filled($tenant['secondary_school_id'] ?? null) || filled($tenant['school_id'] ?? null) => Exam::OWNER_SECONDARY_SCHOOL,
            filled($tenant['professional_school_id'] ?? null) => Exam::OWNER_PROFESSIONAL_SCHOOL,
            filled($tenant['cbt_center_id'] ?? null) || filled($tenant['center_id'] ?? null) => Exam::OWNER_CBT_CENTER,
            default => null,
        };
    }

    private function ownerIdFor(?string $ownerType, array $tenant): ?int
    {
        return match ($ownerType) {
            Exam::OWNER_ORGANIZATION => $tenant['organization_id'] ?? null,
            Exam::OWNER_SECONDARY_SCHOOL => $tenant['secondary_school_id'] ?? $tenant['school_id'] ?? null,
            Exam::OWNER_PROFESSIONAL_SCHOOL => $tenant['professional_school_id'] ?? null,
            Exam::OWNER_CBT_CENTER => $tenant['cbt_center_id'] ?? $tenant['center_id'] ?? null,
            default => null,
        };
    }

    private function tenantPayload(string $ownerType, int|string|null $ownerId, array $tenant = []): array
    {
        $payload = [
            'organization_id' => null,
            'school_id' => null,
            'center_id' => null,
            'secondary_school_id' => null,
            'professional_school_id' => null,
            'cbt_center_id' => null,
            'exam_owner_type' => $ownerType,
            'exam_owner_id' => $ownerId ? (int) $ownerId : null,
        ];

        $payload = [...$payload, ...array_intersect_key($tenant, $payload)];

        match ($ownerType) {
            Exam::OWNER_ORGANIZATION => $payload['organization_id'] = $ownerId,
            Exam::OWNER_SECONDARY_SCHOOL => filled($tenant['school_id'] ?? null) ? $payload['school_id'] = $tenant['school_id'] : $payload['secondary_school_id'] = $ownerId,
            Exam::OWNER_PROFESSIONAL_SCHOOL => $payload['professional_school_id'] = $ownerId,
            Exam::OWNER_CBT_CENTER => filled($tenant['center_id'] ?? null) ? $payload['center_id'] = $tenant['center_id'] : $payload['cbt_center_id'] = $ownerId,
            default => null,
        };

        return $payload;
    }
}
