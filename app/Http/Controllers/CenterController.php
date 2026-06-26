<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCenterRequest;
use App\Http\Requests\UpdateCenterRequest;
use App\Http\Resources\CenterResource;
use App\Models\Center;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CenterController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Center::class);

        return Inertia::render('Centers/Index', [
            'centers' => CenterResource::collection(
                $this->scopedCenters($request)
                    ->latest()
                    ->get()
            ),
            'can' => [
                'create' => $request->user()->can('create', Center::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Center::class);

        return Inertia::render('Centers/Create', [
            'statuses' => $this->statuses(),
        ]);
    }

    public function store(StoreCenterRequest $request): RedirectResponse
    {
        $center = Center::create($request->validated());

        return redirect()
            ->route('centers.show', $center)
            ->with('success', 'Center created.');
    }

    public function show(Request $request, Center $center): Response
    {
        Gate::authorize('view', $center);

        return Inertia::render('Centers/Show', [
            'center' => CenterResource::make($center),
            'can' => [
                'update' => $request->user()->can('update', $center),
                'deactivate' => $request->user()->can('deactivate', $center),
            ],
        ]);
    }

    public function edit(Request $request, Center $center): Response
    {
        Gate::authorize('update', $center);

        return Inertia::render('Centers/Edit', [
            'center' => CenterResource::make($center),
            'statuses' => $this->statuses(),
        ]);
    }

    public function update(UpdateCenterRequest $request, Center $center): RedirectResponse
    {
        $center->update($request->validated());

        return redirect()
            ->route('centers.show', $center)
            ->with('success', 'Center updated.');
    }

    public function deactivate(Center $center): RedirectResponse
    {
        Gate::authorize('deactivate', $center);

        $center->update(['status' => Center::STATUS_INACTIVE]);

        return back()->with('success', 'Center deactivated.');
    }

    private function scopedCenters(Request $request)
    {
        $user = $request->user();

        return Center::query()
            ->when($user->isCenterAdmin(), fn ($query) => $query->where('id', $user->center_id));
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statuses(): array
    {
        return [
            ['value' => Center::STATUS_ACTIVE, 'label' => 'Active'],
            ['value' => Center::STATUS_INACTIVE, 'label' => 'Inactive'],
        ];
    }
}
