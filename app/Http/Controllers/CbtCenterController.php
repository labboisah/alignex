<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\CandidateGroup;
use App\Models\CbtCenter;
use App\Models\Exam;
use App\Models\ExamCenterAssignment;
use App\Models\Organization;
use App\Models\QuestionBank;
use App\Models\Subject;
use App\Support\ReferenceCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CbtCenterController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', CbtCenter::class);

        return Inertia::render('CbtCenters/Index', [
            'centers' => $this->scopedCenters($request)
                ->with('organization:id,name')
                ->withCount(['candidates', 'questionBanks', 'exams'])
                ->orderBy('name')
                ->get()
                ->map(fn (CbtCenter $center) => $this->centerPayload($center)),
            'can' => ['create' => $request->user()->can('create', CbtCenter::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', CbtCenter::class);

        return Inertia::render('CbtCenters/Create', $this->formOptions($request));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', CbtCenter::class);
        $data = $this->validateCenter($request);

        $center = CbtCenter::query()->create($data);

        return redirect()->route('cbt-centers.show', $center)->with('success', 'CBT center created.');
    }

    public function show(Request $request, CbtCenter $cbtCenter): Response
    {
        Gate::authorize('view', $cbtCenter);

        $cbtCenter->load('organization:id,name')->loadCount(['candidates', 'questionBanks', 'exams']);

        return Inertia::render('CbtCenters/Show', [
            'center' => $this->centerPayload($cbtCenter, true),
            'candidates' => $this->candidateRows($cbtCenter),
            'questionBanks' => $this->questionBankRows($cbtCenter),
            'recentExams' => $this->examRows($cbtCenter),
            'assignableExams' => $this->assignableExams($request, $cbtCenter),
            'can' => [
                'update' => $request->user()->can('update', $cbtCenter),
                'manage' => $request->user()->hasPermission('manageCenters'),
            ],
        ]);
    }

    public function edit(Request $request, CbtCenter $cbtCenter): Response
    {
        Gate::authorize('update', $cbtCenter);

        return Inertia::render('CbtCenters/Edit', [
            'center' => $this->centerPayload($cbtCenter->load('organization:id,name')),
            ...$this->formOptions($request),
        ]);
    }

    public function update(Request $request, CbtCenter $cbtCenter): RedirectResponse
    {
        Gate::authorize('update', $cbtCenter);
        $cbtCenter->update($this->validateCenter($request, $cbtCenter));

        return redirect()->route('cbt-centers.show', $cbtCenter)->with('success', 'CBT center updated.');
    }

    public function candidates(Request $request, CbtCenter $cbtCenter): Response
    {
        Gate::authorize('view', $cbtCenter);

        return Inertia::render('CbtCenters/Candidates', [
            'center' => $this->centerPayload($cbtCenter),
            'candidates' => $this->candidateRows($cbtCenter),
            'candidateGroups' => $this->candidateGroupRows($cbtCenter),
        ]);
    }

    public function storeCandidate(Request $request, CbtCenter $cbtCenter): RedirectResponse
    {
        Gate::authorize('update', $cbtCenter);

        $data = $request->validate([
            'registration_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('candidates', 'candidate_number')->where('cbt_center_id', $cbtCenter->id),
            ],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'nin' => ['nullable', 'string', 'max:100'],
            'candidate_group_id' => ['required', 'exists:candidate_groups,id'],
            'status' => ['required', Rule::in([Candidate::STATUS_ACTIVE, Candidate::STATUS_INACTIVE, Candidate::STATUS_SUSPENDED])],
        ]);

        $candidateGroup = CandidateGroup::query()
            ->whereKey($data['candidate_group_id'])
            ->where('cbt_center_id', $cbtCenter->id)
            ->firstOrFail();
        $names = $this->splitName($data['full_name']);

        $candidate = Candidate::query()->create([
            'owner_type' => Exam::OWNER_CBT_CENTER,
            'owner_id' => $cbtCenter->id,
            'organization_id' => $cbtCenter->organization_id,
            'cbt_center_id' => $cbtCenter->id,
            'candidate_number' => strtoupper($data['registration_number']),
            'first_name' => $names[0],
            'last_name' => $names[1],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'nin' => $data['nin'] ?? null,
            'status' => $data['status'],
        ]);

        $candidateGroup->candidates()->syncWithoutDetaching([$candidate->id]);

        return back()->with('success', 'Candidate registered.');
    }

    public function candidatesTemplate(CbtCenter $cbtCenter): HttpResponse
    {
        Gate::authorize('view', $cbtCenter);

        return response("registration_number,full_name,email,phone,nin,status\nCBT-001,Jane Candidate,jane@example.com,08000000000,12345678901,active\n", 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="cbt-candidates-template.csv"',
        ]);
    }

    public function importCandidates(Request $request, CbtCenter $cbtCenter): RedirectResponse
    {
        Gate::authorize('update', $cbtCenter);

        $data = $request->validate([
            'candidate_group_id' => ['required', 'exists:candidate_groups,id'],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);
        $candidateGroup = CandidateGroup::query()
            ->whereKey($data['candidate_group_id'])
            ->where('cbt_center_id', $cbtCenter->id)
            ->firstOrFail();
        $rows = $this->csvRows($data['file']->getRealPath());
        $seen = [];
        $created = [];
        $failed = [];
        $duplicates = [];

        DB::transaction(function () use ($rows, $cbtCenter, $candidateGroup, &$seen, &$created, &$failed, &$duplicates): void {
            foreach ($rows as $line => $row) {
                $registration = strtoupper(trim((string) ($row['registration_number'] ?? '')));
                $fullName = trim((string) ($row['full_name'] ?? ''));

                try {
                    if ($registration === '' || $fullName === '') {
                        throw ValidationException::withMessages(['file' => 'Registration number and full name are required.']);
                    }

                    if (isset($seen[$registration])) {
                        $duplicates[] = ['row' => $line, 'registration_number' => $registration, 'reason' => 'Duplicate in uploaded file.'];
                        continue;
                    }

                    $seen[$registration] = true;

                    if (Candidate::query()->where('cbt_center_id', $cbtCenter->id)->where('candidate_number', $registration)->exists()) {
                        $duplicates[] = ['row' => $line, 'registration_number' => $registration, 'reason' => 'Candidate already exists in this center.'];
                        continue;
                    }

                    $names = $this->splitName($fullName);
                    $candidate = Candidate::query()->create([
                        'owner_type' => Exam::OWNER_CBT_CENTER,
                        'owner_id' => $cbtCenter->id,
                        'organization_id' => $cbtCenter->organization_id,
                        'cbt_center_id' => $cbtCenter->id,
                        'candidate_number' => $registration,
                        'first_name' => $names[0],
                        'last_name' => $names[1],
                        'email' => $row['email'] ?: null,
                        'phone' => $row['phone'] ?: null,
                        'nin' => $row['nin'] ?: null,
                        'status' => in_array($row['status'] ?? 'active', [Candidate::STATUS_ACTIVE, Candidate::STATUS_INACTIVE, Candidate::STATUS_SUSPENDED], true) ? $row['status'] : Candidate::STATUS_ACTIVE,
                    ]);

                    $candidateGroup->candidates()->syncWithoutDetaching([$candidate->id]);

                    $created[] = ['row' => $line, 'registration_number' => $registration, 'name' => $fullName];
                } catch (\Throwable $exception) {
                    $failed[] = ['row' => $line, 'registration_number' => $registration ?: 'N/A', 'reason' => $exception instanceof ValidationException ? 'Required data is missing.' : $exception->getMessage()];
                }
            }
        });

        return back()
            ->with('success', count($created).' candidates imported into '.$candidateGroup->name.'. '.count($duplicates).' duplicates skipped. '.count($failed).' rows failed.')
            ->with('import_summary', compact('created', 'failed', 'duplicates'));
    }

    public function questionBanks(Request $request, CbtCenter $cbtCenter): Response
    {
        Gate::authorize('view', $cbtCenter);

        return Inertia::render('CbtCenters/QuestionBanks', [
            'center' => $this->centerPayload($cbtCenter),
            'subjects' => Subject::query()->where('cbt_center_id', $cbtCenter->id)->orderBy('name')->get(['id', 'name', 'code']),
            'questionBanks' => $this->questionBankRows($cbtCenter),
        ]);
    }

    public function storeQuestionBank(Request $request, CbtCenter $cbtCenter): RedirectResponse
    {
        Gate::authorize('update', $cbtCenter);

        $data = $request->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('question_banks', 'code')->where('cbt_center_id', $cbtCenter->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in([QuestionBank::STATUS_DRAFT, QuestionBank::STATUS_ACTIVE, QuestionBank::STATUS_ARCHIVED])],
        ]);
        abort_unless(Subject::query()->whereKey($data['subject_id'])->where('cbt_center_id', $cbtCenter->id)->exists(), 422);
        $data['code'] = filled($data['code'] ?? null)
            ? strtoupper($data['code'])
            : ReferenceCode::unique($data['name'], QuestionBank::query()->where('cbt_center_id', $cbtCenter->id));

        QuestionBank::query()->create([
            'owner_type' => Exam::OWNER_CBT_CENTER,
            'owner_id' => $cbtCenter->id,
            'organization_id' => $cbtCenter->organization_id,
            'cbt_center_id' => $cbtCenter->id,
            'subject_id' => $data['subject_id'],
            'created_by' => $request->user()->id,
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
        ]);

        return back()->with('success', 'Question bank created.');
    }

    public function assignExternalExam(Request $request, CbtCenter $cbtCenter): RedirectResponse
    {
        Gate::authorize('update', $cbtCenter);

        $data = $request->validate([
            'exam_id' => ['required', 'exists:exams,id'],
            'status' => ['nullable', Rule::in([
                ExamCenterAssignment::STATUS_ASSIGNED,
                ExamCenterAssignment::STATUS_ACTIVE,
                ExamCenterAssignment::STATUS_COMPLETED,
                ExamCenterAssignment::STATUS_CANCELLED,
            ])],
        ]);

        ExamCenterAssignment::query()->updateOrCreate(
            ['exam_id' => $data['exam_id'], 'cbt_center_id' => $cbtCenter->id],
            ['assigned_by' => $request->user()->id, 'status' => $data['status'] ?? ExamCenterAssignment::STATUS_ASSIGNED, 'assigned_at' => now()]
        );

        return back()->with('success', 'Exam assigned to CBT center.');
    }

    private function validateCenter(Request $request, ?CbtCenter $center = null): array
    {
        $data = $request->validate([
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('cbt_centers', 'code')->ignore($center)],
            'location' => ['required', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:0'],
            'contact_person' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('cbt_centers', 'email')->ignore($center)],
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in([CbtCenter::STATUS_ACTIVE, CbtCenter::STATUS_INACTIVE])],
        ]);

        $data['code'] = filled($data['code'] ?? null)
            ? strtoupper($data['code'])
            : ReferenceCode::unique($data['name'], CbtCenter::query(), $center);

        return $data;
    }

    private function formOptions(Request $request): array
    {
        return [
            'organizations' => $request->user()->isSuperAdmin()
                ? Organization::query()->orderBy('name')->get(['id', 'name', 'code'])
                : [],
            'statuses' => [
                ['value' => CbtCenter::STATUS_ACTIVE, 'label' => 'Active'],
                ['value' => CbtCenter::STATUS_INACTIVE, 'label' => 'Inactive'],
            ],
        ];
    }

    private function scopedCenters(Request $request): Builder
    {
        $user = $request->user();

        return CbtCenter::query()
            ->when(! $user->isSuperAdmin(), function (Builder $query) use ($user): void {
                $query->where(function (Builder $inner) use ($user): void {
                    $inner->whereRaw('1 = 0')
                        ->when($user->cbt_center_id, fn (Builder $scope) => $scope->orWhere('id', $user->cbt_center_id))
                        ->when($user->organization_id, fn (Builder $scope) => $scope->orWhere('organization_id', $user->organization_id));
                });
            });
    }

    private function centerPayload(CbtCenter $center, bool $detail = false): array
    {
        return [
            'id' => $center->id,
            'organization_id' => $center->organization_id,
            'organization_name' => $center->organization?->name,
            'name' => $center->name,
            'code' => $center->code,
            'location' => $center->location,
            'capacity' => $center->capacity,
            'contact_person' => $center->contact_person,
            'email' => $center->email,
            'phone' => $center->phone,
            'status' => $center->status,
            'status_label' => str($center->status)->headline()->toString(),
            'candidates_count' => $center->candidates_count ?? null,
            'question_banks_count' => $center->question_banks_count ?? null,
            'exams_count' => $center->exams_count ?? null,
            'detail' => $detail,
        ];
    }

    private function candidateRows(CbtCenter $center): array
    {
        return $center->candidates()
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (Candidate $candidate) => [
                'id' => $candidate->id,
                'registration_number' => $candidate->candidate_number,
                'full_name' => trim($candidate->first_name.' '.$candidate->last_name),
                'email' => $candidate->email,
                'phone' => $candidate->phone,
                'nin' => $candidate->nin,
                'status' => $candidate->status,
            ])
            ->all();
    }

    private function candidateGroupRows(CbtCenter $center): array
    {
        return CandidateGroup::query()
            ->where('cbt_center_id', $center->id)
            ->where('status', CandidateGroup::STATUS_ACTIVE)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (CandidateGroup $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'code' => $group->code,
            ])
            ->all();
    }

    private function questionBankRows(CbtCenter $center): array
    {
        return $center->questionBanks()
            ->with('subject:id,name')
            ->withCount('questions')
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (QuestionBank $bank) => [
                'id' => $bank->id,
                'name' => $bank->name,
                'code' => $bank->code,
                'subject_name' => $bank->subject?->name,
                'description' => $bank->description,
                'status' => $bank->status,
                'questions_count' => $bank->questions_count,
            ])
            ->all();
    }

    private function examRows(CbtCenter $center): array
    {
        return $center->exams()
            ->latest()
            ->limit(8)
            ->get(['id', 'title', 'code', 'exam_category', 'exam_mode', 'mode', 'status'])
            ->map(fn (Exam $exam) => [
                'id' => $exam->id,
                'title' => $exam->title,
                'code' => $exam->code,
                'category' => $exam->exam_category,
                'mode' => $exam->exam_mode ?? $exam->mode,
                'status' => $exam->status,
            ])
            ->all();
    }

    private function assignableExams(Request $request, CbtCenter $center): array
    {
        if (! $request->user()->isSuperAdmin() && ! $request->user()->isOrganizationAdmin()) {
            return [];
        }

        return Exam::query()
            ->when($center->organization_id, fn (Builder $query) => $query->where('organization_id', $center->organization_id))
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'title', 'code'])
            ->map(fn (Exam $exam) => ['id' => $exam->id, 'name' => $exam->title, 'code' => $exam->code])
            ->all();
    }

    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), 2) ?: [];

        return [$parts[0] ?? $fullName, $parts[1] ?? ''];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function csvRows(string $path): array
    {
        $handle = fopen($path, 'rb');
        $headers = $handle ? fgetcsv($handle) : false;
        $rows = [];
        $line = 1;

        if (! $handle || ! $headers) {
            return [];
        }

        $headers = array_map(fn ($header) => str(trim((string) $header))->snake()->toString(), $headers);

        while (($values = fgetcsv($handle)) !== false) {
            $line++;
            $rows[$line] = array_combine($headers, array_pad($values, count($headers), '')) ?: [];
        }

        fclose($handle);

        return $rows;
    }
}
