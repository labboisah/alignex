<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdaptiveOfflinePackageRequest;
use App\Http\Requests\AdaptivePilotRequest;
use App\Models\AdaptiveOfflineLease;
use App\Models\AdaptiveOfflinePackage;
use App\Models\Exam;
use App\Models\OfflineServerActivation;
use App\Services\AdaptiveOfflinePilotService;
use App\Services\AdaptivePilotService;
use App\Services\AdaptiveRolloutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class AdaptivePilotController extends Controller
{
    private function access(Exam $exam): void
    {
        Gate::authorize('update', $exam);
        Gate::authorize('viewAdaptiveReport', $exam);
    }

    public function show(Request $request, Exam $exam, AdaptivePilotService $service)
    {
        $this->access($exam);
        $owner = app(AdaptiveRolloutService::class)->ownerKey($exam);
        $packages = AdaptiveOfflinePackage::where('exam_id', $exam->id)->where('owner_key', $owner)->latest()->limit(30)->get();

        return Inertia::render('Exams/AdaptivePilot', [
            'exam' => ['id' => $exam->id, 'title' => $exam->title], 'control' => $service->control($exam),
            'rollout' => app(AdaptiveRolloutService::class)->status($exam),
            'candidates' => $exam->candidates()->get(['candidates.id', 'candidate_number', 'first_name', 'last_name']),
            'packages' => $packages->map(fn ($p) => ['id' => $p->id, 'activation_id' => $p->activation_id, 'created_at' => $p->created_at,
                'access' => $p->payload['candidates'], 'leases' => AdaptiveOfflineLease::where('package_id', $p->id)->get(['id', 'candidate_id', 'status', 'result', 'error_code'])]),
        ]);
    }

    public function update(AdaptivePilotRequest $request, Exam $exam, AdaptivePilotService $service)
    {
        $this->access($exam);
        $service->save($exam, $request->user(), $request->validated());

        return back()->with('success', 'Diagnostic pilot controls saved. Existing started sessions retain their state.');
    }

    public function package(AdaptiveOfflinePackageRequest $request, Exam $exam, AdaptiveOfflinePilotService $service)
    {
        $this->access($exam);
        $service->create($exam, $request->user(), OfflineServerActivation::findOrFail($request->integer('activation_id')), $request->validated('candidate_ids'));

        return back()->with('success', 'Offline package reserved. Import its ID on the assigned center server.');
    }
}
