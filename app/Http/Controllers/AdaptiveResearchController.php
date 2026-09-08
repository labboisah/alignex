<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdaptiveResearchRequest;
use App\Models\AdaptiveCalibration;
use App\Models\AdaptiveEngineRun;
use App\Models\AdaptiveLevel;
use App\Models\AdaptiveProgression;
use App\Models\AdaptiveSnapshot;
use App\Models\Exam;
use App\Services\AdaptiveCalibrationService;
use App\Services\AdaptiveRolloutService;
use App\Services\AdaptiveShadowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class AdaptiveResearchController extends Controller
{
    public function show(Request $request, Exam $exam)
    {
        Gate::authorize('viewAdaptiveReport', $exam);
        $snapshots = AdaptiveSnapshot::where('exam_id', $exam->id)->where('owner_key', app(AdaptiveRolloutService::class)->ownerKey($exam))->pluck('id');

        return Inertia::render('Exams/AdaptiveResearch', [
            'exam' => ['id' => $exam->id, 'title' => $exam->title],
            'snapshots' => AdaptiveSnapshot::whereIn('id', $snapshots)->get(['id', 'version', 'ready']),
            'can_manage' => $request->user()->can('update', $exam),
            'shadow_enabled' => (bool) config('adaptive.engine.shadow_enabled'),
            'calibrations' => AdaptiveCalibration::whereIn('snapshot_id', $snapshots)->latest()->limit(50)->get()
                ->map(fn ($c) => ['id' => $c->id, 'snapshot_id' => $c->snapshot_id, 'version' => $c->version, 'status' => $c->status,
                    'fingerprint' => $c->fingerprint, 'specialist' => $c->payload['specialist'], 'source_reference' => $c->payload['source_reference'],
                    'criteria_reference' => $c->payload['criteria_reference'], 'validation_notes' => $c->payload['validation_notes']]),
            'levels' => AdaptiveLevel::whereIn('progression_id', AdaptiveProgression::where('exam_id', $exam->id)->whereIn('snapshot_id', $snapshots)->select('id'))
                ->latest('id')->limit(100)->get(['id', 'number', 'progression_id', 'status']),
            'runs' => AdaptiveEngineRun::whereIn('calibration_id', AdaptiveCalibration::whereIn('snapshot_id', $snapshots)->select('id'))
                ->latest()->limit(50)->get()->map(fn ($r) => ['id' => $r->id, 'level_id' => $r->level_id, 'status' => $r->status,
                    'error_code' => $r->error_code, 'replay_of' => $r->replay_of, 'duration_ms' => $r->duration_ms, 'result' => $r->result]),
        ]);
    }

    public function template(Request $request, Exam $exam, AdaptiveSnapshot $snapshot)
    {
        Gate::authorize('viewAdaptiveReport', $exam);
        Gate::authorize('update', $exam);
        abort_unless($snapshot->exam_id === $exam->id && $snapshot->owner_key === app(AdaptiveRolloutService::class)->ownerKey($exam), 404);

        return response()->json([
            'schema_version' => 'calibration-2pl-v1', 'snapshot_id' => $snapshot->id,
            'source_reference' => '', 'specialist' => '', 'sample_size' => null,
            'criteria_reference' => '', 'validation_notes' => '',
            'policy' => ['min_questions' => count($snapshot->blueprint['areas']), 'max_questions' => $snapshot->settings['adaptive_max_questions'],
                'min_per_area' => 1, 'target_sd' => null, 'cutpoint' => null],
            'items' => $snapshot->items()->get()->map(fn ($item) => ['id' => $item->id, 'content_hash' => $item->content_hash,
                'a' => null, 'b' => null, 'exposure_limit' => null]),
        ], 200, ['Content-Disposition' => 'attachment; filename="calibration-template-'.$snapshot->id.'.json"', 'Cache-Control' => 'private, no-store']);
    }

    public function import(AdaptiveResearchRequest $request, Exam $exam, AdaptiveCalibrationService $service)
    {
        $service->import($exam, $request->user(), json_decode($request->validated('payload'), true, 512, JSON_THROW_ON_ERROR));

        return back()->with('success', 'Calibration draft imported. Independent review is required for shadow use.');
    }

    public function transition(AdaptiveResearchRequest $request, Exam $exam, AdaptiveCalibration $calibration, AdaptiveCalibrationService $service)
    {
        $service->transition($exam, $request->user(), $calibration, $request->validated('action'));

        return back()->with('success', 'Calibration status updated. Consequential scoring remains disabled.');
    }

    public function evaluate(AdaptiveResearchRequest $request, Exam $exam, AdaptiveShadowService $service)
    {
        $run = $service->evaluate($exam, $request->user(), AdaptiveCalibration::findOrFail($request->validated('calibration_id')), AdaptiveLevel::findOrFail($request->validated('level_id')));

        return back()->with($run->status === 'succeeded' ? 'success' : 'error', $run->status === 'succeeded' ? 'Shadow evaluation recorded. Candidate results are unchanged.' : 'Shadow evaluation failed: '.$run->error_code);
    }

    public function replay(AdaptiveResearchRequest $request, Exam $exam, AdaptiveEngineRun $run, AdaptiveShadowService $service)
    {
        $result = $service->evaluate($exam, $request->user(), AdaptiveCalibration::findOrFail($run->calibration_id), AdaptiveLevel::findOrFail($run->level_id), $run);

        return back()->with($result->status === 'succeeded' ? 'success' : 'error', $result->status === 'succeeded' ? 'Replay matched the original result.' : 'Replay failed: '.$result->error_code);
    }
}
