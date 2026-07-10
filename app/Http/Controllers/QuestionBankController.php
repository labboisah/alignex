<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuestionBankRequest;
use App\Http\Requests\UpdateQuestionBankRequest;
use App\Http\Resources\QuestionBankResource;
use App\Http\Resources\SubjectResource;
use App\Models\Center;
use App\Models\Exam;
use App\Models\Organization;
use App\Models\QuestionBank;
use App\Models\School;
use App\Models\Subject;
use App\Support\ReferenceCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class QuestionBankController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', QuestionBank::class);

        return Inertia::render('QuestionBanks/Index', [
            'questionBanks' => QuestionBankResource::collection(
                $this->scopedQuestionBanks($request)
                    ->with(['organization', 'school', 'center', 'subject', 'secondarySchool', 'professionalSchool', 'cbtCenter', 'programme', 'course', 'module'])
                    ->withCount('questions')
                    ->latest()
                    ->get()
            ),
            'can' => [
                'create' => $request->user()->can('create', QuestionBank::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', QuestionBank::class);

        return Inertia::render('QuestionBanks/Create', [
            'statuses' => $this->statuses(),
            'subjects' => SubjectResource::collection($this->scopedSubjects($request)->orderBy('name')->get()),
            ...$this->scopeOptions($request),
        ]);
    }

    public function store(StoreQuestionBankRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $subject = $this->authorizedSubject($request, $data['subject_id']);
        $tenant = $this->tenantFromSubject($subject);
        $data['code'] = $this->referenceCode($data['code'] ?? null, $data['name'], $tenant);
        $this->ensureUniqueCode($data['code'], $tenant);

        QuestionBank::create([
            ...$data,
            ...$tenant,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('question-bank.index')->with('success', 'Question bank created.');
    }

    public function show(Request $request, QuestionBank $questionBank): Response
    {
        Gate::authorize('view', $questionBank);

        return Inertia::render('QuestionBanks/Show', [
            'questionBank' => QuestionBankResource::make($questionBank->load(['organization', 'school', 'center', 'subject', 'secondarySchool', 'professionalSchool', 'cbtCenter', 'programme', 'course', 'module'])->loadCount('questions')),
            'can' => [
                'update' => $request->user()->can('update', $questionBank),
                'delete' => $request->user()->can('delete', $questionBank),
            ],
        ]);
    }

    public function edit(Request $request, QuestionBank $questionBank): Response
    {
        Gate::authorize('update', $questionBank);

        return Inertia::render('QuestionBanks/Edit', [
            'questionBank' => QuestionBankResource::make($questionBank->load(['organization', 'school', 'center', 'subject', 'secondarySchool', 'professionalSchool', 'cbtCenter', 'programme', 'course', 'module'])->loadCount('questions')),
            'statuses' => $this->statuses(),
            'subjects' => SubjectResource::collection($this->scopedSubjects($request)->orderBy('name')->get()),
        ]);
    }

    public function update(UpdateQuestionBankRequest $request, QuestionBank $questionBank): RedirectResponse
    {
        $data = $request->validated();
        $subject = $this->authorizedSubject($request, $data['subject_id']);
        $tenant = $this->tenantFromSubject($subject);
        $data['code'] = $this->referenceCode($data['code'] ?? null, $data['name'], $tenant, $questionBank);
        $this->ensureUniqueCode($data['code'], $tenant, $questionBank);

        $questionBank->update([
            ...$data,
            ...$tenant,
        ]);

        return redirect()->route('question-bank.index')->with('success', 'Question bank updated.');
    }

    public function destroy(QuestionBank $questionBank): RedirectResponse
    {
        Gate::authorize('delete', $questionBank);

        $questionBank->delete();

        return back()->with('success', 'Question bank deleted.');
    }

    public function template()
    {
        Gate::authorize('create', QuestionBank::class);

        return response()->streamDownload(function (): void {
            echo "subject_code,name,code,description,status\n";
            echo "MATH,Mathematics Main Bank,MATH-MAIN,Approved mathematics question source,draft\n";
        }, 'question-banks-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request): RedirectResponse
    {
        Gate::authorize('create', QuestionBank::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $rows = $this->csvRows($request->file('file')->getRealPath());
        $created = 0;

        DB::transaction(function () use ($request, $rows, &$created): void {
            foreach ($rows as $index => $row) {
                $subject = $this->subjectByCode($request, trim($row['subject_code'] ?? ''), $index + 2);
                $tenant = $this->tenantFromSubject($subject);
                $data = [
                    ...$tenant,
                    'subject_id' => $subject->id,
                    'name' => trim($row['name'] ?? ''),
                    'code' => strtoupper(trim($row['code'] ?? '')),
                    'description' => $row['description'] ?? null,
                    'status' => trim($row['status'] ?? QuestionBank::STATUS_DRAFT) ?: QuestionBank::STATUS_DRAFT,
                    'created_by' => $request->user()->id,
                ];

                validator($data, (new StoreQuestionBankRequest())->rules())->validate();
                $this->ensureUniqueCode($data['code'], $tenant, null, $index + 2);

                QuestionBank::create($data);
                $created++;
            }
        });

        return back()->with('success', "{$created} question banks imported.");
    }

    private function scopedQuestionBanks(Request $request)
    {
        $user = $request->user();
        $organization = $request->route('organization');

        return QuestionBank::query()
            ->when($organization, fn ($query) => $query->where('organization_id', $organization->id))
            ->when($user->isTeacher(), fn ($query) => $query->whereIn('subject_id', $user->assignedSubjects()->select('subjects.id')))
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
            ->when($user->isTeacher(), fn ($query) => $query->whereIn('id', $user->assignedSubjects()->select('subjects.id')))
            ->when(! $user->isSuperAdmin() && $user->organization_id, fn ($query) => $query->where('organization_id', $user->organization_id))
            ->when(! $user->isSuperAdmin() && $user->school_id, fn ($query) => $query->where('school_id', $user->school_id))
            ->when(! $user->isSuperAdmin() && $user->center_id, fn ($query) => $query->where('center_id', $user->center_id))
            ->when(! $user->isSuperAdmin() && $user->secondary_school_id, fn ($query) => $query->where('secondary_school_id', $user->secondary_school_id))
            ->when(! $user->isSuperAdmin() && $user->professional_school_id, fn ($query) => $query->where('professional_school_id', $user->professional_school_id))
            ->when(! $user->isSuperAdmin() && $user->cbt_center_id, fn ($query) => $query->where('cbt_center_id', $user->cbt_center_id));
    }

    private function authorizedSubject(Request $request, string $subjectId): Subject
    {
        $subject = $this->scopedSubjects($request)->whereKey($subjectId)->first();

        if (! $subject) {
            throw ValidationException::withMessages(['subject_id' => 'Choose a subject within your allowed scope.']);
        }

        return $subject;
    }

    private function subjectByCode(Request $request, string $code, int $row): Subject
    {
        $subject = $this->scopedSubjects($request)->where('code', strtoupper($code))->first();

        if (! $subject) {
            throw ValidationException::withMessages(['file' => "Row {$row}: Subject code was not found in your scope."]);
        }

        return $subject;
    }

    private function ensureUniqueCode(string $code, array $tenant, ?QuestionBank $ignore = null, ?int $row = null): void
    {
        $exists = QuestionBank::query()
            ->where('code', $code)
            ->where('organization_id', $tenant['organization_id'])
            ->where('school_id', $tenant['school_id'])
            ->where('center_id', $tenant['center_id'])
            ->where('secondary_school_id', $tenant['secondary_school_id'] ?? null)
            ->where('professional_school_id', $tenant['professional_school_id'] ?? null)
            ->where('cbt_center_id', $tenant['cbt_center_id'] ?? null)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
            ->exists();

        if ($exists) {
            $prefix = $row ? "Row {$row}: " : '';
            throw ValidationException::withMessages(['code' => "{$prefix}The question bank code is already in use for this scope."]);
        }
    }

    private function referenceCode(?string $code, string $name, array $tenant, ?QuestionBank $ignore = null): string
    {
        if (filled($code)) {
            return strtoupper($code);
        }

        return ReferenceCode::unique($name, QuestionBank::query()
            ->where('organization_id', $tenant['organization_id'])
            ->where('school_id', $tenant['school_id'])
            ->where('center_id', $tenant['center_id'])
            ->where('secondary_school_id', $tenant['secondary_school_id'] ?? null)
            ->where('professional_school_id', $tenant['professional_school_id'] ?? null)
            ->where('cbt_center_id', $tenant['cbt_center_id'] ?? null), $ignore);
    }

    private function tenantFromSubject(Subject $subject): array
    {
        return [
            'owner_type' => match (true) {
                filled($subject->secondary_school_id) => Exam::OWNER_SECONDARY_SCHOOL,
                filled($subject->professional_school_id) => Exam::OWNER_PROFESSIONAL_SCHOOL,
                filled($subject->cbt_center_id) => Exam::OWNER_CBT_CENTER,
                filled($subject->organization_id) => Exam::OWNER_ORGANIZATION,
                default => null,
            },
            'owner_id' => $subject->secondary_school_id
                ?? $subject->professional_school_id
                ?? $subject->cbt_center_id
                ?? $subject->organization_id,
            'organization_id' => $subject->organization_id,
            'school_id' => $subject->school_id,
            'center_id' => $subject->center_id,
            'secondary_school_id' => $subject->secondary_school_id,
            'professional_school_id' => $subject->professional_school_id,
            'cbt_center_id' => $subject->cbt_center_id,
        ];
    }

    private function csvRows(string $path): array
    {
        $handle = fopen($path, 'r');
        $headers = array_map(fn ($header) => strtolower(trim((string) $header)), fgetcsv($handle) ?: []);
        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, fn ($value) => filled($value))) === 0) {
                continue;
            }

            $rows[] = array_combine($headers, array_pad($row, count($headers), null));
        }

        fclose($handle);

        return $rows;
    }

    private function scopeOptions(Request $request): array
    {
        return [
            'organizations' => $request->user()->isSuperAdmin()
                ? Organization::query()->orderBy('name')->get(['id', 'name', 'code'])
                : [],
            'schools' => $request->user()->isSuperAdmin()
                ? School::query()->orderBy('name')->get(['id', 'name', 'code'])
                : [],
            'centers' => $request->user()->isSuperAdmin()
                ? Center::query()->orderBy('name')->get(['id', 'name', 'code'])
                : [],
        ];
    }

    private function statuses(): array
    {
        return [
            ['value' => QuestionBank::STATUS_DRAFT, 'label' => 'Draft'],
            ['value' => QuestionBank::STATUS_ACTIVE, 'label' => 'Active'],
            ['value' => QuestionBank::STATUS_ARCHIVED, 'label' => 'Archived'],
        ];
    }
}
