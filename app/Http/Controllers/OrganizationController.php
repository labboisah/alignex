<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Organization::class);

        return Inertia::render('Organizations/Index', [
            'organizations' => OrganizationResource::collection(
                Organization::query()
                    ->latest()
                    ->get()
            ),
            'can' => [
                'create' => $request->user()->can('create', Organization::class),
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Organization::class);

        return Inertia::render('Organizations/Create', [
            'statuses' => $this->statuses(),
        ]);
    }

    public function store(StoreOrganizationRequest $request): RedirectResponse
    {
        $organization = Organization::create($request->validated());

        return redirect()
            ->route('organizations.show', $organization)
            ->with('success', 'Organization created.');
    }

    public function show(Request $request, Organization $organization): Response
    {
        Gate::authorize('view', $organization);

        return Inertia::render('Organizations/Show', [
            'organization' => OrganizationResource::make($organization),
            'can' => [
                'update' => $request->user()->can('update', $organization),
                'deactivate' => $request->user()->can('deactivate', $organization),
            ],
        ]);
    }

    public function edit(Organization $organization): Response
    {
        Gate::authorize('update', $organization);

        return Inertia::render('Organizations/Edit', [
            'organization' => OrganizationResource::make($organization),
            'statuses' => $this->statuses(),
        ]);
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization): RedirectResponse
    {
        $organization->update($request->validated());

        return redirect()
            ->route('organizations.show', $organization)
            ->with('success', 'Organization updated.');
    }

    public function deactivate(Organization $organization): RedirectResponse
    {
        Gate::authorize('deactivate', $organization);

        $organization->update(['status' => Organization::STATUS_INACTIVE]);

        return back()->with('success', 'Organization deactivated.');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statuses(): array
    {
        return [
            ['value' => Organization::STATUS_ACTIVE, 'label' => 'Active'],
            ['value' => Organization::STATUS_INACTIVE, 'label' => 'Inactive'],
        ];
    }
}
