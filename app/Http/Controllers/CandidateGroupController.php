<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\CandidateGroup;
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
                ->with('candidates:id,candidate_number,first_name,last_name')
                ->withCount('candidates')
                ->latest()
                ->get()
                ->map(fn (CandidateGroup $group) => $this->row($group)),
            'statuses' => [
                ['value' => CandidateGroup::STATUS_ACTIVE, 'label' => 'Active'],
                ['value' => CandidateGroup::STATUS_INACTIVE, 'label' => 'Inactive'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        $data = $this->validated($request);

        DB::transaction(function () use ($request, $data): void {
            CandidateGroup::query()->create([
                'organization_id' => $this->organizationId($request),
                'cbt_center_id' => $this->cbtCenterId($request),
                'name' => $data['name'],
                'code' => filled($data['code'] ?? null)
                    ? strtoupper($data['code'])
                    : ReferenceCode::unique($data['name'], $this->scopedGroups($request)),
                'description' => $data['description'] ?? null,
                'status' => $data['status'],
            ]);
        });

        return back()->with('success', 'Candidate group created.');
    }

    public function update(Request $request, CandidateGroup $candidateGroup): RedirectResponse
    {
        $this->authorizeAccess($request);
        abort_unless($this->scopedGroups($request)->whereKey($candidateGroup->id)->exists(), 403);

        $data = $this->validated($request, $candidateGroup);

        DB::transaction(function () use ($candidateGroup, $data): void {
            $candidateGroup->update([
                'name' => $data['name'],
                'code' => filled($data['code'] ?? null) ? strtoupper($data['code']) : $candidateGroup->code,
                'description' => $data['description'] ?? null,
                'status' => $data['status'],
            ]);
        });

        return back()->with('success', 'Candidate group updated.');
    }

    public function destroy(Request $request, CandidateGroup $candidateGroup): RedirectResponse
    {
        $this->authorizeAccess($request);
        abort_unless($this->scopedGroups($request)->whereKey($candidateGroup->id)->exists(), 403);

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

        return CandidateGroup::query()
            ->when($organizationId, fn (Builder $query) => $query->where('organization_id', $organizationId))
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
            ->when($this->cbtCenterId($request), fn (Builder $query, string|int $centerId) => $query->where('cbt_center_id', $centerId));
    }

    private function organizationId(Request $request): ?string
    {
        $user = $request->user();

        return $user->isSuperAdmin() ? null : $user->organization_id;
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

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('candidate_groups', 'code')
                    ->ignore($candidateGroup)
                    ->where(fn ($query) => $query
                        ->where('organization_id', $organizationId)
                        ->when($this->hasCandidateGroupColumn('cbt_center_id'), fn ($scope) => $cbtCenterId ? $scope->where('cbt_center_id', $cbtCenterId) : $scope->whereNull('cbt_center_id'))),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in([CandidateGroup::STATUS_ACTIVE, CandidateGroup::STATUS_INACTIVE])],
        ]);
    }

    private function row(CandidateGroup $group): array
    {
        return [
            'id' => $group->id,
            'name' => $group->name,
            'code' => $group->code,
            'description' => $group->description,
            'status' => $group->status,
            'candidates_count' => $group->candidates_count ?? 0,
            'candidate_ids' => $group->candidates->pluck('id')->values()->all(),
            'candidates' => $group->candidates->map(fn (Candidate $candidate) => [
                'id' => $candidate->id,
                'name' => trim($candidate->first_name.' '.$candidate->last_name),
                'registration_number' => $candidate->candidate_number,
            ])->values()->all(),
        ];
    }
}
