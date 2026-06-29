<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubjectRequest;
use App\Http\Requests\UpdateSubjectRequest;
use App\Http\Resources\SubjectResource;
use App\Models\Center;
use App\Models\Organization;
use App\Models\School;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SubjectController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Subject::class);

        return Inertia::render('Subjects/Index', [
            'subjects' => SubjectResource::collection(
                $this->scopedSubjects($request)
                    ->with(['organization', 'school', 'center'])
                    ->withCount(['topics', 'questionBanks'])
                    ->latest()
                    ->get()
            ),
            'can' => [
                'create' => $request->user()->can('create', Subject::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Subject::class);

        return Inertia::render('Subjects/Create', [
            'statuses' => $this->statuses(),
            ...$this->scopeOptions($request),
        ]);
    }

    public function store(StoreSubjectRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $tenant = $this->tenantFor($request, $data);
        $this->ensureUniqueCode($data['code'], $tenant);

        Subject::create([
            ...$data,
            ...$tenant,
        ]);

        return redirect()->route('subjects.index')->with('success', 'Subject created.');
    }

    public function edit(Request $request, Subject $subject): Response
    {
        Gate::authorize('update', $subject);

        return Inertia::render('Subjects/Edit', [
            'subject' => SubjectResource::make($subject->load(['organization', 'school', 'center'])->loadCount(['topics', 'questionBanks'])),
            'statuses' => $this->statuses(),
        ]);
    }

    public function update(UpdateSubjectRequest $request, Subject $subject): RedirectResponse
    {
        $data = $request->validated();
        $tenant = ['organization_id' => $subject->organization_id, 'school_id' => $subject->school_id, 'center_id' => $subject->center_id];
        $this->ensureUniqueCode($data['code'], $tenant, $subject);

        $subject->update($data);

        return redirect()->route('subjects.index')->with('success', 'Subject updated.');
    }

    public function destroy(Subject $subject): RedirectResponse
    {
        Gate::authorize('delete', $subject);

        $subject->delete();

        return back()->with('success', 'Subject deleted.');
    }

    public function template()
    {
        Gate::authorize('create', Subject::class);

        return response()->streamDownload(function (): void {
            echo "name,code,description,status,scope_type,scope_code\n";
            echo "Mathematics,MATH,Core mathematics,active,,\n";
        }, 'subjects-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request): RedirectResponse
    {
        Gate::authorize('create', Subject::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $rows = $this->csvRows($request->file('file')->getRealPath());
        $created = 0;

        DB::transaction(function () use ($request, $rows, &$created): void {
            foreach ($rows as $index => $row) {
                $data = [
                    'name' => trim($row['name'] ?? ''),
                    'code' => strtoupper(trim($row['code'] ?? '')),
                    'description' => $row['description'] ?? null,
                    'status' => trim($row['status'] ?? Subject::STATUS_ACTIVE) ?: Subject::STATUS_ACTIVE,
                    'organization_id' => $row['organization_id'] ?? null,
                    'school_id' => $row['school_id'] ?? null,
                    'center_id' => $row['center_id'] ?? null,
                    'scope_type' => $row['scope_type'] ?? null,
                    'scope_code' => $row['scope_code'] ?? null,
                ];

                validator($data, (new StoreSubjectRequest())->rules())->validate();

                $tenant = $this->tenantFor($request, $data, $index + 2);
                $this->ensureUniqueCode($data['code'], $tenant, null, $index + 2);

                Subject::create([
                    'name' => $data['name'],
                    'code' => $data['code'],
                    'description' => $data['description'],
                    'status' => $data['status'],
                    ...$tenant,
                ]);
                $created++;
            }
        });

        return back()->with('success', "{$created} subjects imported.");
    }

    private function scopedSubjects(Request $request)
    {
        $user = $request->user();

        return Subject::query()
            ->when(! $user->isSuperAdmin() && $user->organization_id, fn ($query) => $query->where('organization_id', $user->organization_id))
            ->when(! $user->isSuperAdmin() && $user->school_id, fn ($query) => $query->where('school_id', $user->school_id))
            ->when(! $user->isSuperAdmin() && $user->center_id, fn ($query) => $query->where('center_id', $user->center_id));
    }

    private function tenantFor(Request $request, array $data, ?int $row = null): array
    {
        $user = $request->user();

        if ($user->isOrganizationAdmin() || ($user->isExaminer() && $user->organization_id)) {
            return ['organization_id' => $user->organization_id, 'school_id' => null, 'center_id' => null];
        }

        if ($user->isSchoolAdmin() || ($user->isExaminer() && $user->school_id)) {
            return ['organization_id' => null, 'school_id' => $user->school_id, 'center_id' => null];
        }

        if ($user->isCenterAdmin() || ($user->isExaminer() && $user->center_id)) {
            return ['organization_id' => null, 'school_id' => null, 'center_id' => $user->center_id];
        }

        $organizationId = $data['organization_id'] ?: null;
        $schoolId = $data['school_id'] ?: null;
        $centerId = $data['center_id'] ?: null;
        $scopeType = str($data['scope_type'] ?? '')->lower()->toString();
        $scopeCode = trim((string) ($data['scope_code'] ?? ''));

        if ($user->isSuperAdmin() && $scopeType && $scopeCode) {
            return match ($scopeType) {
                'organization', 'org' => [
                    'organization_id' => Organization::query()->where('code', $scopeCode)->value('id')
                        ?? throw ValidationException::withMessages(['scope_code' => ($row ? "Row {$row}: " : '').'Organization code was not found.']),
                    'school_id' => null,
                    'center_id' => null,
                ],
                'school' => [
                    'organization_id' => null,
                    'school_id' => School::query()->where('code', $scopeCode)->value('id')
                        ?? throw ValidationException::withMessages(['scope_code' => ($row ? "Row {$row}: " : '').'School code was not found.']),
                    'center_id' => null,
                ],
                'center' => [
                    'organization_id' => null,
                    'school_id' => null,
                    'center_id' => Center::query()->where('code', $scopeCode)->value('id')
                        ?? throw ValidationException::withMessages(['scope_code' => ($row ? "Row {$row}: " : '').'Center code was not found.']),
                ],
                default => throw ValidationException::withMessages(['scope_type' => ($row ? "Row {$row}: " : '').'Use organization, school, or center as the scope type.']),
            };
        }

        if ($user->isSuperAdmin() && collect([$organizationId, $schoolId, $centerId])->filter(fn ($value) => filled($value))->count() === 1) {
            return ['organization_id' => $organizationId, 'school_id' => $schoolId, 'center_id' => $centerId];
        }

        $prefix = $row ? "Row {$row}: " : '';
        throw ValidationException::withMessages([
            'organization_id' => "{$prefix}Choose exactly one organization, school, or center scope.",
        ]);
    }

    private function ensureUniqueCode(string $code, array $tenant, ?Subject $ignore = null, ?int $row = null): void
    {
        $exists = Subject::query()
            ->where('code', $code)
            ->where('organization_id', $tenant['organization_id'])
            ->where('school_id', $tenant['school_id'])
            ->where('center_id', $tenant['center_id'])
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
            ->exists();

        if ($exists) {
            $prefix = $row ? "Row {$row}: " : '';
            throw ValidationException::withMessages(['code' => "{$prefix}The subject code is already in use for this scope."]);
        }
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
            'scope' => [
                'type' => match (true) {
                    $request->user()->isOrganizationAdmin() => 'organization',
                    $request->user()->isSchoolAdmin() => 'school',
                    $request->user()->isCenterAdmin() => 'center',
                    default => 'select',
                },
            ],
        ];
    }

    private function statuses(): array
    {
        return [
            ['value' => Subject::STATUS_ACTIVE, 'label' => 'Active'],
            ['value' => Subject::STATUS_INACTIVE, 'label' => 'Inactive'],
        ];
    }
}
