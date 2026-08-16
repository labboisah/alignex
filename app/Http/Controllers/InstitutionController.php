<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Support\ReferenceCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InstitutionController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isSuperAdmin() || $request->user()->hasPermission('manageSchools'), 403);

        $query = Institution::query()->withCount(['faculties', 'departments', 'programmes', 'courses']);

        if ($request->user()->isInstitutionAdmin()) {
            $query->whereKey($request->user()->institution_id);
        }

        return Inertia::render('Institutions/Index', [
            'institutions' => $query->latest()->get(),
            'can' => [
                'create' => $request->user()->isSuperAdmin(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        return Inertia::render('Institutions/Create', [
            'statuses' => $this->statuses(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('institutions', 'code')],
            'institution_type' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('institutions', 'email')],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in([Institution::STATUS_ACTIVE, Institution::STATUS_INACTIVE])],
        ]);

        $data['code'] = filled($data['code'] ?? null)
            ? strtoupper($data['code'])
            : ReferenceCode::unique($data['name'], Institution::query());

        $institution = Institution::query()->create($data);

        return redirect()->route('institutions.show', $institution)->with('success', 'Institution created.');
    }

    public function show(Request $request, Institution $institution): Response
    {
        $this->authorizeInstitution($request->user(), $institution);

        $institution->loadCount(['faculties', 'departments', 'programmes', 'courses', 'questionBanks']);

        return Inertia::render('Institutions/Show', [
            'institution' => $institution,
            'can' => [
                'update' => $request->user()->isSuperAdmin() || ($request->user()->isInstitutionAdmin() && (int) $request->user()->institution_id === (int) $institution->id),
            ],
        ]);
    }

    public function edit(Request $request, Institution $institution): Response
    {
        $this->authorizeInstitution($request->user(), $institution);

        return Inertia::render('Institutions/Edit', [
            'institution' => $institution,
            'statuses' => $this->statuses(),
        ]);
    }

    public function update(Request $request, Institution $institution): RedirectResponse
    {
        $this->authorizeInstitution($request->user(), $institution);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('institutions', 'code')->ignore($institution->id)],
            'institution_type' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('institutions', 'email')->ignore($institution->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in([Institution::STATUS_ACTIVE, Institution::STATUS_INACTIVE])],
        ]);

        $data['code'] = filled($data['code'] ?? null)
            ? strtoupper($data['code'])
            : ReferenceCode::unique($data['name'], Institution::query(), $institution);

        $institution->update($data);

        return redirect()->route('institutions.show', $institution)->with('success', 'Institution updated.');
    }

    public function deactivate(Request $request, Institution $institution): RedirectResponse
    {
        $this->authorizeInstitution($request->user(), $institution);

        $institution->update(['status' => Institution::STATUS_INACTIVE]);

        return back()->with('success', 'Institution deactivated.');
    }

    private function authorizeInstitution($user, Institution $institution): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        abort_unless($user->isInstitutionAdmin() && (int) $user->institution_id === (int) $institution->id, 403);
    }

    private function statuses(): array
    {
        return [
            ['value' => Institution::STATUS_ACTIVE, 'label' => 'Active'],
            ['value' => Institution::STATUS_INACTIVE, 'label' => 'Inactive'],
        ];
    }
}
