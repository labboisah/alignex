<?php

namespace App\Http\Controllers;

use App\Http\Requests\PrepareAdaptiveSnapshotRequest;
use App\Http\Resources\AdaptiveReadinessResource;
use App\Models\AdaptiveSnapshot;
use App\Models\Exam;
use App\Services\AdaptivePreparationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AdaptivePreparationController extends Controller
{
    public function show(Request $request, Exam $exam, AdaptivePreparationService $service): Response
    {
        Gate::authorize('update', $exam);
        $data = $service->inspect($exam);

        return Inertia::render('Exams/AdaptivePreparation', [
            'exam' => ['id' => $exam->id, 'title' => $exam->title],
            'readiness' => (new AdaptiveReadinessResource($data['readiness']))->resolve($request),
            'snapshots' => AdaptiveSnapshot::where('exam_id', $exam->id)->latest('version')
                ->get(['id', 'version', 'ready', 'created_at']),
        ]);
    }

    public function store(PrepareAdaptiveSnapshotRequest $request, Exam $exam, AdaptivePreparationService $service): RedirectResponse
    {
        $snapshot = $service->prepare($exam, $request->user()->id);

        return back()->with('success', 'Adaptive snapshot version '.$snapshot->version.' saved. Live delivery remains disabled.');
    }
}
