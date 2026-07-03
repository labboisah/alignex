<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSchoolRequest;
use App\Http\Requests\UpdateSchoolRequest;
use App\Http\Resources\SchoolResource;
use App\Models\School;
use App\Support\ReferenceCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SchoolController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', School::class);

        return Inertia::render('Schools/Index', [
            'schools' => SchoolResource::collection(
                $this->scopedSchools($request)->latest()->get()
            ),
            'can' => [
                'create' => $request->user()->can('create', School::class),
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', School::class);

        return Inertia::render('Schools/Create', [
            'statuses' => $this->statuses(),
        ]);
    }

    public function store(StoreSchoolRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['code'] = filled($data['code'] ?? null) ? strtoupper($data['code']) : ReferenceCode::unique($data['name'], School::query());
        $school = School::create($data);

        return redirect()
            ->route('schools.show', $school)
            ->with('success', 'School created.');
    }

    public function show(Request $request, School $school): Response
    {
        Gate::authorize('view', $school);

        return Inertia::render('Schools/Show', [
            'school' => SchoolResource::make($school),
            'can' => [
                'update' => $request->user()->can('update', $school),
                'deactivate' => $request->user()->can('deactivate', $school),
            ],
        ]);
    }

    public function edit(School $school): Response
    {
        Gate::authorize('update', $school);

        return Inertia::render('Schools/Edit', [
            'school' => SchoolResource::make($school),
            'statuses' => $this->statuses(),
        ]);
    }

    public function update(UpdateSchoolRequest $request, School $school): RedirectResponse
    {
        $data = $request->validated();
        $data['code'] = filled($data['code'] ?? null) ? strtoupper($data['code']) : ReferenceCode::unique($data['name'], School::query(), $school);
        $school->update($data);

        return redirect()
            ->route('schools.show', $school)
            ->with('success', 'School updated.');
    }

    public function deactivate(School $school): RedirectResponse
    {
        Gate::authorize('deactivate', $school);

        $school->update(['status' => School::STATUS_INACTIVE]);

        return back()->with('success', 'School deactivated.');
    }

    private function scopedSchools(Request $request)
    {
        $user = $request->user();

        return School::query()
            ->when($user->isSchoolAdmin(), fn ($query) => $query->where('id', $user->school_id));
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statuses(): array
    {
        return [
            ['value' => School::STATUS_ACTIVE, 'label' => 'Active'],
            ['value' => School::STATUS_INACTIVE, 'label' => 'Inactive'],
        ];
    }
}
