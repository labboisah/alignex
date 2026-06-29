<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCandidateRequest;
use App\Http\Requests\UpdateCandidateRequest;
use App\Http\Resources\CandidateResource;
use App\Http\Resources\ExamResource;
use App\Models\Candidate;
use App\Models\Center;
use App\Models\Exam;
use App\Models\Organization;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CandidateController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Candidate::class);

        return Inertia::render('Candidates/Index', [
            'candidates' => CandidateResource::collection(
                $this->scopedCandidates($request)
                    ->with(['organization', 'school', 'center'])
                    ->withCount('assignedExams')
                    ->latest()
                    ->get()
            ),
            'exams' => ExamResource::collection($this->scopedExams($request)->with('examType')->latest()->get()),
            'importReport' => $request->session()->get('candidate_import_report'),
            'can' => ['create' => $request->user()->can('create', Candidate::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Candidate::class);

        return Inertia::render('Candidates/Create', [
            ...$this->formOptions($request),
        ]);
    }

    public function store(StoreCandidateRequest $request): RedirectResponse
    {
        $candidate = $this->persistCandidate($request);

        return redirect()->route('candidates.show', $candidate)->with('success', 'Candidate created.');
    }

    public function show(Candidate $candidate): Response
    {
        Gate::authorize('view', $candidate);

        return Inertia::render('Candidates/Show', [
            'candidate' => CandidateResource::make($candidate->load(['organization', 'school', 'center', 'assignedExams.examType'])),
        ]);
    }

    public function edit(Request $request, Candidate $candidate): Response
    {
        Gate::authorize('update', $candidate);

        return Inertia::render('Candidates/Edit', [
            'candidate' => CandidateResource::make($candidate->load(['organization', 'school', 'center'])),
            ...$this->formOptions($request),
        ]);
    }

    public function update(UpdateCandidateRequest $request, Candidate $candidate): RedirectResponse
    {
        $this->persistCandidate($request, $candidate);

        return redirect()->route('candidates.show', $candidate)->with('success', 'Candidate updated.');
    }

    public function destroy(Candidate $candidate): RedirectResponse
    {
        Gate::authorize('delete', $candidate);

        $candidate->delete();

        return redirect()->route('candidates.index')->with('success', 'Candidate deleted.');
    }

    public function template(): StreamedResponse
    {
        Gate::authorize('create', Candidate::class);

        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['full_name', 'registration_number', 'email', 'phone', 'date_of_birth', 'status']);
            fputcsv($output, ['Ada Okafor', 'REG-001', 'ada@example.com', '08030000000', '2008-04-12', 'active']);
            fclose($output);
        }, 'candidate_import_template.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request): RedirectResponse
    {
        Gate::authorize('create', Candidate::class);

        $data = $request->validate([
            'exam_id' => ['required', 'exists:exams,id'],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:4096'],
        ]);

        $exam = $this->scopedExams($request)->whereKey($data['exam_id'])->firstOrFail();
        $tenant = $this->tenantForExam($exam);
        $rows = $this->csvRows($request->file('file')->getRealPath());
        $created = [];
        $failed = [];
        $duplicates = [];
        $seen = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $registrationNumber = strtoupper(trim((string) ($row['registration_number'] ?? '')));

            try {
                if ($registrationNumber === '') {
                    throw ValidationException::withMessages(['registration_number' => 'Registration number is required.']);
                }

                $duplicateKey = implode(':', [$tenant['organization_id'] ?? '', $tenant['school_id'] ?? '', $tenant['center_id'] ?? '', $registrationNumber]);

                if (isset($seen[$duplicateKey])) {
                    $duplicates[] = ['row' => $line, 'registration_number' => $registrationNumber, 'reason' => 'Duplicate in uploaded file.'];
                    continue;
                }

                $seen[$duplicateKey] = true;

                if ($this->candidateExists($tenant, $registrationNumber)) {
                    $duplicates[] = ['row' => $line, 'registration_number' => $registrationNumber, 'reason' => 'Candidate already exists.'];
                    continue;
                }

                $fullName = trim((string) ($row['full_name'] ?? trim((string) ($row['first_name'] ?? '').' '.(string) ($row['last_name'] ?? ''))));
                [$firstName, $lastName] = $this->splitFullName($fullName);

                validator([
                    'full_name' => $fullName,
                    'status' => filled($row['status'] ?? null) ? strtolower(trim((string) $row['status'])) : Candidate::STATUS_ACTIVE,
                    'email' => $row['email'] ?? null,
                    'date_of_birth' => $row['date_of_birth'] ?? null,
                ], [
                    'full_name' => ['required', 'string', 'max:255'],
                    'status' => [Rule::in([Candidate::STATUS_ACTIVE, Candidate::STATUS_INACTIVE, Candidate::STATUS_SUSPENDED])],
                    'email' => ['nullable', 'email'],
                    'date_of_birth' => ['nullable', 'date'],
                ])->validate();

                $candidate = Candidate::create([
                    ...$tenant,
                    'candidate_number' => $registrationNumber,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => filled($row['email'] ?? null) ? trim((string) $row['email']) : null,
                    'phone' => filled($row['phone'] ?? null) ? trim((string) $row['phone']) : null,
                    'date_of_birth' => filled($row['date_of_birth'] ?? null) ? $row['date_of_birth'] : null,
                    'status' => filled($row['status'] ?? null) ? strtolower(trim((string) $row['status'])) : Candidate::STATUS_ACTIVE,
                    'metadata' => ['source' => 'csv_import'],
                ]);

                $exam->candidates()->syncWithoutDetaching([
                    $candidate->id => ['status' => 'assigned'],
                ]);

                $created[] = ['row' => $line, 'registration_number' => $registrationNumber, 'name' => trim($candidate->first_name.' '.$candidate->last_name)];
            } catch (\Throwable $exception) {
                $failed[] = ['row' => $line, 'registration_number' => $registrationNumber ?: 'N/A', 'reason' => $exception instanceof ValidationException ? $exception->errors()[array_key_first($exception->errors())][0] : $exception->getMessage()];
            }
        }

        $report = [
            'successful' => $created,
            'failed' => $failed,
            'duplicates' => $duplicates,
            'error_report_url' => $this->writeErrorReport([...$failed, ...$duplicates]),
        ];

        return back()
            ->with('success', count($created).' candidates imported and assigned to '.$exam->title.'.')
            ->with('candidate_import_report', $report);
    }

    public function assignments(Request $request): Response
    {
        Gate::authorize('viewAny', Candidate::class);

        $exam = $request->filled('exam_id')
            ? $this->scopedExams($request)->whereKey($request->exam_id)->first()
            : $this->scopedExams($request)->latest()->first();

        return Inertia::render('Candidates/Assignments', [
            'exams' => ExamResource::collection($this->scopedExams($request)->with('examType')->latest()->get()),
            'selectedExam' => $exam ? ExamResource::make($exam->load('examType')) : null,
            'candidates' => CandidateResource::collection($this->scopedCandidates($request)->with(['organization', 'school', 'center'])->withCount('assignedExams')->orderBy('last_name')->get()),
            'assignedCandidates' => $exam ? CandidateResource::collection($exam->candidates()->with(['organization', 'school', 'center'])->withCount('assignedExams')->orderBy('last_name')->get()) : ['data' => []],
        ]);
    }

    public function assign(Request $request): RedirectResponse
    {
        Gate::authorize('create', Candidate::class);

        $data = $request->validate([
            'exam_id' => ['required', 'exists:exams,id'],
            'candidate_ids' => ['required', 'array', 'min:1'],
            'candidate_ids.*' => ['required', 'exists:candidates,id'],
        ]);

        $exam = $this->scopedExams($request)->whereKey($data['exam_id'])->firstOrFail();
        $candidateIds = $this->scopedCandidates($request)->whereIn('id', $data['candidate_ids'])->pluck('id');

        DB::transaction(function () use ($exam, $candidateIds): void {
            foreach ($candidateIds as $candidateId) {
                $exam->candidates()->syncWithoutDetaching([
                    $candidateId => ['status' => 'assigned'],
                ]);
            }
        });

        return back()->with('success', $candidateIds->count().' candidates assigned.');
    }

    public function unassign(Request $request, Exam $exam, Candidate $candidate): RedirectResponse
    {
        Gate::authorize('update', $exam);
        Gate::authorize('update', $candidate);

        $exam->candidates()->detach($candidate->id);

        return back()->with('success', 'Candidate removed from exam.');
    }

    public function errorReport(string $filename): mixed
    {
        Gate::authorize('create', Candidate::class);

        $path = 'candidate-import-errors/'.basename($filename);
        abort_unless(Storage::exists($path), 404);

        return Storage::download($path);
    }

    private function persistCandidate(StoreCandidateRequest|UpdateCandidateRequest $request, ?Candidate $candidate = null): Candidate
    {
        $data = $request->validated();
        $tenant = $candidate ? ['organization_id' => $candidate->organization_id, 'school_id' => $candidate->school_id, 'center_id' => $candidate->center_id] : $this->tenantFor($request, $data);
        $metadata = $candidate?->metadata ?? [];
        [$firstName, $lastName] = $this->splitFullName($data['full_name'] ?? trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')));

        if ($request->hasFile('photo')) {
            $metadata['photo_path'] = $request->file('photo')->store('candidate-photos', 'public');
        }

        $payload = [
            ...$tenant,
            'candidate_number' => strtoupper($data['registration_number']),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'metadata' => $metadata,
            'status' => $data['status'],
        ];

        $candidate ? $candidate->update($payload) : $candidate = Candidate::create($payload);

        return $candidate;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitFullName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), 2) ?: [];

        return [
            $parts[0] ?? '',
            $parts[1] ?? '',
        ];
    }

    private function scopedCandidates(Request $request)
    {
        $user = $request->user();
        $organization = $request->route('organization');

        return Candidate::query()
            ->when($organization, fn ($query) => $query->where('organization_id', $organization->id))
            ->when(! $user->isSuperAdmin() && $user->organization_id, fn ($query) => $query->where('organization_id', $user->organization_id))
            ->when(! $user->isSuperAdmin() && $user->school_id, fn ($query) => $query->where('school_id', $user->school_id))
            ->when(! $user->isSuperAdmin() && $user->center_id, fn ($query) => $query->where('center_id', $user->center_id));
    }

    private function scopedExams(Request $request)
    {
        $user = $request->user();
        $organization = $request->route('organization');

        return Exam::query()
            ->when($organization, fn ($query) => $query->where('organization_id', $organization->id))
            ->when(! $user->isSuperAdmin() && $user->organization_id, fn ($query) => $query->where('organization_id', $user->organization_id))
            ->when(! $user->isSuperAdmin() && $user->school_id, fn ($query) => $query->where('school_id', $user->school_id))
            ->when(! $user->isSuperAdmin() && $user->center_id, fn ($query) => $query->where('center_id', $user->center_id));
    }

    private function tenantFor(Request $request, array $data): array
    {
        $user = $request->user();

        if ($user->isOrganizationAdmin() || ($user->isExaminer() && $user->organization_id)) {
            return ['organization_id' => $user->organization_id, 'school_id' => null, 'center_id' => null];
        }

        if ($request->route('organization') && $user->isSuperAdmin()) {
            return ['organization_id' => $request->route('organization')->id, 'school_id' => null, 'center_id' => null];
        }

        if ($user->isSchoolAdmin() || ($user->isExaminer() && $user->school_id)) {
            return ['organization_id' => null, 'school_id' => $user->school_id, 'center_id' => null];
        }

        if ($user->isCenterAdmin() || ($user->isExaminer() && $user->center_id)) {
            return ['organization_id' => null, 'school_id' => null, 'center_id' => $user->center_id];
        }

        $tenant = ['organization_id' => $data['organization_id'] ?: null, 'school_id' => $data['school_id'] ?: null, 'center_id' => $data['center_id'] ?: null];

        if ($user->isSuperAdmin() && collect($tenant)->filter(fn ($value) => filled($value))->count() === 1) {
            return $tenant;
        }

        throw ValidationException::withMessages(['organization_id' => 'Choose exactly one organization, school, or center scope.']);
    }

    private function tenantForExam(Exam $exam): array
    {
        return [
            'organization_id' => $exam->organization_id,
            'school_id' => $exam->school_id,
            'center_id' => $exam->center_id,
        ];
    }

    private function candidateExists(array $tenant, string $registrationNumber): bool
    {
        return Candidate::query()
            ->where('candidate_number', $registrationNumber)
            ->when($tenant['organization_id'] ?? null, fn ($query, $organizationId) => $query->where('organization_id', $organizationId))
            ->when($tenant['school_id'] ?? null, fn ($query, $schoolId) => $query->where('school_id', $schoolId))
            ->when($tenant['center_id'] ?? null, fn ($query, $centerId) => $query->where('center_id', $centerId))
            ->exists();
    }

    private function formOptions(Request $request): array
    {
        return [
            'organizations' => $request->user()->isSuperAdmin() ? Organization::query()->orderBy('name')->get(['id', 'name', 'code']) : [],
            'schools' => $request->user()->isSuperAdmin() ? School::query()->orderBy('name')->get(['id', 'name', 'code']) : [],
            'centers' => $request->user()->isSuperAdmin() ? Center::query()->orderBy('name')->get(['id', 'name', 'code']) : [],
            'statuses' => [
                ['value' => Candidate::STATUS_ACTIVE, 'label' => 'Active'],
                ['value' => Candidate::STATUS_INACTIVE, 'label' => 'Inactive'],
                ['value' => Candidate::STATUS_SUSPENDED, 'label' => 'Suspended'],
            ],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function csvRows(string $path): array
    {
        $handle = fopen($path, 'r');
        $headers = array_map(fn ($header) => str($header)->lower()->trim()->replace(' ', '_')->toString(), fgetcsv($handle) ?: []);
        $rows = [];

        while (($values = fgetcsv($handle)) !== false) {
            if (count(array_filter($values, fn ($value) => filled($value))) === 0) {
                continue;
            }

            $rows[] = array_combine($headers, array_pad($values, count($headers), '')) ?: [];
        }

        fclose($handle);

        return $rows;
    }

    private function writeErrorReport(array $rows): ?string
    {
        if ($rows === []) {
            return null;
        }

        $filename = 'candidate_import_errors_'.now()->format('Ymd_His').'.csv';
        $path = 'candidate-import-errors/'.$filename;
        $content = "row,registration_number,reason\n";

        foreach ($rows as $row) {
            $content .= implode(',', array_map(fn ($value) => '"'.str_replace('"', '""', (string) $value).'"', [$row['row'], $row['registration_number'], $row['reason']]))."\n";
        }

        Storage::put($path, $content);

        return route('candidates.import-errors', ['filename' => $filename]);
    }
}
