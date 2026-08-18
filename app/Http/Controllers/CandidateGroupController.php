<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\CandidateGroup;
use App\Models\Department;
use App\Services\CurrentContextService;
use App\Support\ReferenceCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CandidateGroupController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        return Inertia::render('CandidateGroups/Index', [
            'groups' => $this->scopedGroups($request)
                ->with(['department:id,name,code', 'candidates:id,candidate_number,first_name,last_name'])
                ->withCount('candidates')
                ->latest()
                ->get()
                ->map(fn (CandidateGroup $group) => $this->row($group)),
            'statuses' => [
                ['value' => CandidateGroup::STATUS_ACTIVE, 'label' => 'Active'],
                ['value' => CandidateGroup::STATUS_INACTIVE, 'label' => 'Inactive'],
            ],
            'departments' => $this->departmentOptions($request),
            'candidates' => $this->candidateOptions($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        $data = $this->validated($request);

        DB::transaction(function () use ($request, $data): void {
            $group = CandidateGroup::query()->create([
                'organization_id' => $this->organizationId($request),
                ...$this->institutionPayload($request, $data['department_id'] ?? null),
                'cbt_center_id' => $this->cbtCenterId($request),
                'created_by' => $request->user()->id,
                'name' => $data['name'],
                'code' => filled($data['code'] ?? null)
                    ? strtoupper($data['code'])
                    : ReferenceCode::unique($data['name'], $this->scopedGroups($request)),
                'description' => $data['description'] ?? null,
                'status' => $data['status'],
            ]);

            $this->syncCandidates($request, $group, $data['candidate_ids'] ?? []);
        });

        return back()->with('success', 'Candidate group created.');
    }

    public function update(Request $request, CandidateGroup $candidateGroup): RedirectResponse
    {
        $this->authorizeAccess($request);
        abort_unless($this->scopedGroups($request)->whereKey($candidateGroup->id)->exists(), 403);
        abort_unless($this->canManageGroup($request, $candidateGroup), 403);

        $data = $this->validated($request, $candidateGroup);

        DB::transaction(function () use ($request, $candidateGroup, $data): void {
            $candidateGroup->update([
                ...$this->institutionPayload($request, $data['department_id'] ?? null),
                'name' => $data['name'],
                'code' => filled($data['code'] ?? null) ? strtoupper($data['code']) : $candidateGroup->code,
                'description' => $data['description'] ?? null,
                'status' => $data['status'],
            ]);

            $this->syncCandidates($request, $candidateGroup, $data['candidate_ids'] ?? []);
        });

        return back()->with('success', 'Candidate group updated.');
    }

    public function destroy(Request $request, CandidateGroup $candidateGroup): RedirectResponse
    {
        $this->authorizeAccess($request);
        abort_unless($this->scopedGroups($request)->whereKey($candidateGroup->id)->exists(), 403);
        abort_unless($this->canManageGroup($request, $candidateGroup), 403);

        $candidateGroup->delete();

        return back()->with('success', 'Candidate group deleted.');
    }

    private function authorizeAccess(Request $request): void
    {
        abort_unless($request->user()?->hasPermission('manageExams'), 403);
    }

    private function scopedGroups(Request $request): Builder
    {
        $organizationId = $this->organizationId($request);
        $institutionId = $this->institutionId($request);
        $departmentId = $request->query('department_id');

        return CandidateGroup::query()
            ->when($organizationId, fn (Builder $query) => $query->where('organization_id', $organizationId))
            ->when($institutionId, fn (Builder $query) => $query->where('institution_id', $institutionId))
            ->when(! $institutionId, fn (Builder $query) => $query->whereNull('institution_id'))
            ->when($departmentId && $institutionId, fn (Builder $query) => $query->where('department_id', $departmentId))
            ->when($request->user()->isInstitutionLecturer(), fn (Builder $query) => $query->where('department_id', $request->user()->department_id))
            ->when($this->hasCandidateGroupColumn('cbt_center_id'), function (Builder $query) use ($request): void {
                $centerId = $this->cbtCenterId($request);

                $centerId
                    ? $query->where('cbt_center_id', $centerId)
                    : $query->whereNull('cbt_center_id');
            });
    }

    private function scopedCandidates(Request $request): Builder
    {
        $organizationId = $this->organizationId($request);

        return Candidate::query()
            ->when($organizationId, fn (Builder $query) => $query->where('organization_id', $organizationId))
            ->when($this->institutionId($request), fn (Builder $query, string|int $institutionId) => $query->where('institution_id', $institutionId))
            ->when(! $this->institutionId($request), fn (Builder $query) => $query->whereNull('institution_id'))
            ->when($request->query('department_id'), fn (Builder $query, string|int $departmentId) => $query->where('department_id', $departmentId))
            ->when($request->user()->isInstitutionLecturer(), fn (Builder $query) => $query->where('department_id', $request->user()->department_id))
            ->when($this->cbtCenterId($request), fn (Builder $query, string|int $centerId) => $query->where('cbt_center_id', $centerId));
    }

    private function organizationId(Request $request): ?string
    {
        $user = $request->user();

        if ($this->institutionId($request)) {
            return null;
        }

        return $user->isSuperAdmin() ? null : $user->organization_id;
    }

    private function institutionId(Request $request): ?int
    {
        $user = $request->user();
        $context = app(CurrentContextService::class)->current($user);

        return (($context['type'] ?? null) === 'institution' ? (int) $context['id'] : null)
            ?? ($user->institution_id ? (int) $user->institution_id : null);
    }

    private function cbtCenterId(Request $request): ?int
    {
        $user = $request->user();
        $context = app(CurrentContextService::class)->current($user);

        if (($context['type'] ?? null) === 'cbt_center') {
            return (int) $context['id'];
        }

        return $user->cbt_center_id ? (int) $user->cbt_center_id : null;
    }

    private function hasCandidateGroupColumn(string $column): bool
    {
        return Schema::hasTable('candidate_groups') && Schema::hasColumn('candidate_groups', $column);
    }

    private function validated(Request $request, ?CandidateGroup $candidateGroup = null): array
    {
        $organizationId = $this->organizationId($request);
        $cbtCenterId = $this->cbtCenterId($request);
        $institutionId = $this->institutionId($request);

        return $request->validate([
            'department_id' => [$institutionId ? 'required' : 'nullable', 'integer', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('candidate_groups', 'code')
                    ->ignore($candidateGroup)
                    ->where(fn ($query) => $query
                        ->when(! $institutionId, fn ($scope) => $scope->where('organization_id', $organizationId))
                        ->when($institutionId, fn ($scope) => $scope->where('institution_id', $institutionId))
                        ->when($institutionId && $request->input('department_id'), fn ($scope) => $scope->where('department_id', $request->input('department_id')))
                        ->when($this->hasCandidateGroupColumn('cbt_center_id'), fn ($scope) => $cbtCenterId ? $scope->where('cbt_center_id', $cbtCenterId) : $scope->whereNull('cbt_center_id'))),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in([CandidateGroup::STATUS_ACTIVE, CandidateGroup::STATUS_INACTIVE])],
            'candidate_ids' => ['sometimes', 'array'],
            'candidate_ids.*' => ['required', 'exists:candidates,id'],
        ]);
    }

    private function row(CandidateGroup $group): array
    {
        return [
            'id' => $group->id,
            'name' => $group->name,
            'code' => $group->code,
            'department_id' => $group->department_id,
            'department_name' => $group->department?->name,
            'description' => $group->description,
            'status' => $group->status,
            'candidates_count' => $group->candidates_count ?? 0,
            'candidate_ids' => $group->candidates->pluck('id')->values()->all(),
            'can_manage' => $this->canManageGroup(request(), $group),
            'candidates' => $group->candidates->map(fn (Candidate $candidate) => [
                'id' => $candidate->id,
                'name' => trim($candidate->first_name.' '.$candidate->last_name),
                'registration_number' => $candidate->candidate_number,
            ])->values()->all(),
        ];
    }

    private function institutionPayload(Request $request, int|string|null $departmentId): array
    {
        $institutionId = $this->institutionId($request);

        if (! $institutionId) {
            return [
                'institution_id' => null,
                'faculty_id' => null,
                'department_id' => null,
            ];
        }

        $department = Department::query()
            ->whereKey($departmentId)
            ->where('institution_id', $institutionId)
            ->when($request->user()->isInstitutionLecturer(), fn ($query) => $query->whereKey($request->user()->department_id))
            ->firstOrFail();

        return [
            'organization_id' => $department->institution?->organization_id,
            'institution_id' => $department->institution_id,
            'faculty_id' => $department->faculty_id,
            'department_id' => $department->id,
        ];
    }

    private function departmentOptions(Request $request): array
    {
        $institutionId = $this->institutionId($request);

        if (! $institutionId) {
            return [];
        }

        return Department::query()
            ->where('institution_id', $institutionId)
            ->when($request->user()->isInstitutionLecturer(), fn ($query) => $query->whereKey($request->user()->department_id))
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Department $department) => [
                'id' => $department->id,
                'name' => $department->name,
                'code' => $department->code,
            ])
            ->all();
    }

    private function candidateOptions(Request $request): array
    {
        return $this->scopedCandidates($request)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'candidate_number', 'first_name', 'last_name', 'department_id'])
            ->map(fn (Candidate $candidate) => [
                'id' => $candidate->id,
                'department_id' => $candidate->department_id,
                'name' => trim($candidate->first_name.' '.$candidate->last_name),
                'registration_number' => $candidate->candidate_number,
            ])
            ->all();
    }

    private function syncCandidates(Request $request, CandidateGroup $group, array $candidateIds): void
    {
        $allowedIds = $this->scopedCandidates($request)
            ->whereIn('id', $candidateIds)
            ->pluck('id')
            ->all();

        $group->candidates()->sync($allowedIds);
    }

    private function canManageGroup(Request $request, CandidateGroup $group): bool
    {
        $user = $request->user();

        if (! $user?->isInstitutionLecturer()) {
            return true;
        }

        return (string) $group->created_by === (string) $user->id;
    }
}
