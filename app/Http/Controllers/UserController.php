<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\CbtCenter;
use App\Models\Organization;
use App\Models\ProfessionalSchool;
use App\Models\SecondarySchool;
use App\Models\User;
use App\Support\AccessControl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('viewAny', User::class), 403);

        $query = User::query()
            ->with(['organization:id,name', 'secondarySchool:id,name', 'professionalSchool:id,name', 'cbtCenter:id,name'])
            ->latest();

        if (! $request->user()?->isSuperAdmin()) {
            $query->where('organization_id', $request->user()?->organization_id);
        }

        return Inertia::render('Users/Index', [
            'users' => UserResource::collection($query->get()),
            'options' => [
                'roles' => collect(AccessControl::roles())
                    ->except([User::ROLE_CANDIDATE, User::ROLE_STUDENT])
                    ->when(! $request->user()?->isSuperAdmin(), fn ($roles) => $roles->except([User::ROLE_SUPER_ADMIN]))
                    ->map(fn (array $role, string $key): array => ['value' => $key, 'label' => $role['label']])
                    ->values(),
                'statuses' => collect(User::STATUSES)->map(fn (string $status): array => [
                    'value' => $status,
                    'label' => str($status)->headline()->toString(),
                ])->values(),
                'organizations' => Organization::query()
                    ->when(! $request->user()?->isSuperAdmin(), fn ($builder) => $builder->whereKey($request->user()?->organization_id))
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'secondary_schools' => $this->optionQuery(SecondarySchool::query(), $request)->get(['id', 'name', 'organization_id']),
                'professional_schools' => $this->optionQuery(ProfessionalSchool::query(), $request)->get(['id', 'name', 'organization_id']),
                'cbt_centers' => $this->optionQuery(CbtCenter::query(), $request)->get(['id', 'name', 'organization_id']),
            ],
            'can' => [
                'create' => $request->user()?->can('create', User::class) ?? false,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('create', User::class), 403);

        User::create($this->validated($request));

        return redirect()->route('users.index')->with('success', 'User created.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->can('update', $user), 403);

        $validated = $this->validated($request, $user);

        if (($validated['status'] ?? User::STATUS_ACTIVE) === User::STATUS_INACTIVE && $request->user()?->is($user)) {
            return back()->withErrors(['status' => 'You cannot deactivate your own current account.']);
        }

        $user->update($validated);

        return redirect()->route('users.index')->with('success', 'User updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->can('delete', $user), 403);

        if ($request->user()?->is($user)) {
            return back()->withErrors(['user' => 'You cannot delete your own current account.']);
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $user = null): array
    {
        $roles = array_values(array_diff(array_keys(AccessControl::roles()), [User::ROLE_CANDIDATE, User::ROLE_STUDENT]));

        if (! $request->user()?->isSuperAdmin()) {
            $roles = array_values(array_diff($roles, [User::ROLE_SUPER_ADMIN]));
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($user)],
            'role' => ['required', Rule::in($roles)],
            'status' => ['required', Rule::in(User::STATUSES)],
            'organization_id' => ['nullable', 'integer', Rule::exists(Organization::class, 'id')],
            'secondary_school_id' => ['nullable', 'integer', Rule::exists(SecondarySchool::class, 'id')],
            'professional_school_id' => ['nullable', 'integer', Rule::exists(ProfessionalSchool::class, 'id')],
            'cbt_center_id' => ['nullable', 'integer', Rule::exists(CbtCenter::class, 'id')],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'max:255'],
        ]);

        if (! $request->user()?->isSuperAdmin()) {
            $validated['organization_id'] = $request->user()?->organization_id;
        }

        foreach (['organization_id', 'secondary_school_id', 'professional_school_id', 'cbt_center_id'] as $key) {
            $validated[$key] = blank($validated[$key] ?? null) ? null : (int) $validated[$key];
        }

        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        } else {
            $validated['password'] = Hash::make($validated['password']);
        }

        $this->validateOwnership($request, $validated);

        return $validated;
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function validateOwnership(Request $request, array $validated): void
    {
        $organizationId = $validated['organization_id'] ?? null;

        if ($secondarySchoolId = $validated['secondary_school_id'] ?? null) {
            $this->validateLinkedOrganization(SecondarySchool::class, $secondarySchoolId, $organizationId);
        }

        if ($professionalSchoolId = $validated['professional_school_id'] ?? null) {
            $this->validateLinkedOrganization(ProfessionalSchool::class, $professionalSchoolId, $organizationId);
        }

        if ($cbtCenterId = $validated['cbt_center_id'] ?? null) {
            $this->validateLinkedOrganization(CbtCenter::class, $cbtCenterId, $organizationId);
        }

        if (! $request->user()?->isSuperAdmin() && $organizationId !== $request->user()?->organization_id) {
            abort(403);
        }
    }

    private function validateLinkedOrganization(string $model, int $id, ?int $organizationId): void
    {
        abort_unless($model::query()
            ->whereKey($id)
            ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId))
            ->exists(), 422, 'The selected institution does not belong to the selected organization.');
    }

    private function optionQuery($query, Request $request)
    {
        return $query
            ->when(! $request->user()?->isSuperAdmin(), fn ($builder) => $builder->where('organization_id', $request->user()?->organization_id))
            ->orderBy('name');
    }
}
