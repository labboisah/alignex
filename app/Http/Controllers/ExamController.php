<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExamRequest;
use App\Http\Requests\UpdateExamRequest;
use App\Http\Resources\ExamResource;
use App\Http\Resources\SubjectResource;
use App\Models\Center;
use App\Models\CbtCenter;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\Organization;
use App\Models\ProfessionalSchool;
use App\Models\School;
use App\Models\SecondarySchool;
use App\Models\Subject;
use App\Services\ExamStatusService;
use App\Support\ExamOwnershipRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
                    ->with(['organization', 'school', 'center', 'examType'])
                    ->withCount('examSubjects')
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
            'exam' => ExamResource::make($exam->load(['organization', 'school', 'center', 'examType', 'examSubjects.subject'])),
            'can' => [
                'update' => $request->user()->can('update', $exam),
                'cancel' => $request->user()->can('update', $exam) && $exam->status !== Exam::STATUS_CANCELLED,
            ],
        ]);
    }

    public function edit(Request $request, Exam $exam): Response
    {
        Gate::authorize('update', $exam);
        $exam = app(ExamStatusService::class)->sync($exam);

        return Inertia::render('Exams/Edit', [
            'exam' => ExamResource::make($exam->load(['organization', 'school', 'center', 'examType', 'examSubjects.subject'])),
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

        $exam ? $exam->update($payload) : $exam = Exam::create($payload);
        $exam->examSubjects()->delete();

        foreach (array_values($data['subjects']) as $index => $subject) {
            $this->authorizeSubject($request, $subject['subject_id']);
            $exam->examSubjects()->create([
                'subject_id' => $subject['subject_id'],
                'display_order' => $index + 1,
                'question_count' => $subject['number_of_questions'],
                'marks_per_question' => $subject['marks_per_question'],
                'total_marks' => (float) $subject['number_of_questions'] * (float) $subject['marks_per_question'],
                'duration_minutes' => $subject['duration_minutes'] ?? null,
                'difficulty_distribution' => $subject['difficulty_distribution'] ?? null,
            ]);
        }

        return $exam;
    }

    private function scopedExams(Request $request)
    {
        $user = $request->user();
        $organization = $request->route('organization');

        return Exam::query()
            ->when($organization, fn ($query) => $query->where('organization_id', $organization->id))
            ->when($request->filled('category'), fn ($query) => $query->where('exam_category', $request->query('category')))
            ->when($request->filled('mode'), fn ($query) => $query->where(fn ($inner) => $inner->where('exam_mode', $request->query('mode'))->orWhere('mode', $request->query('mode'))))
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

        return Subject::query()
            ->when($organization, fn ($query) => $query->where('organization_id', $organization->id))
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

        if ($user->isOrganizationAdmin() || ($user->isExaminer() && $user->organization_id)) {
            return $this->tenantPayload(Exam::OWNER_ORGANIZATION, $user->organization_id);
        }

        if ($request->route('organization') && $user->isSuperAdmin()) {
            return $this->tenantPayload(Exam::OWNER_ORGANIZATION, $request->route('organization')->id);
        }

        if ($user->isSecondarySchoolAdmin() || ($user->isExaminer() && ($user->secondary_school_id || $user->school_id))) {
            return $user->secondary_school_id
                ? $this->tenantPayload(Exam::OWNER_SECONDARY_SCHOOL, $user->secondary_school_id)
                : $this->tenantPayload(Exam::OWNER_SECONDARY_SCHOOL, $user->school_id, ['school_id' => $user->school_id]);
        }

        if ($user->isProfessionalSchoolAdmin() || ($user->isExaminer() && $user->professional_school_id)) {
            return $this->tenantPayload(Exam::OWNER_PROFESSIONAL_SCHOOL, $user->professional_school_id);
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
            'professionalSchools' => $request->user()->isSuperAdmin() ? ProfessionalSchool::query()->orderBy('name')->get(['id', 'name', 'code']) : [],
            'cbtCenters' => $request->user()->isSuperAdmin() ? CbtCenter::query()->orderBy('name')->get(['id', 'name', 'code']) : [],
            'examTypes' => [
                ['value' => 'secondary', 'label' => 'Secondary'],
                ['value' => 'professional', 'label' => 'Professional'],
                ['value' => 'recruitment', 'label' => 'Recruitment'],
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
            default => Exam::CATEGORY_GENERAL,
        };
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
