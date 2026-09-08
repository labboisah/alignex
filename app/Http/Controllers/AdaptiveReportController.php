<?php

namespace App\Http\Controllers;

use App\Models\AdaptiveProgression;
use App\Models\Exam;
use App\Services\AdaptiveReportService;
use App\Services\AdaptiveRolloutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class AdaptiveReportController extends Controller
{
    public function index(Request $request, Exam $exam)
    {
        Gate::authorize('viewAdaptiveReport', $exam);
        $rows = AdaptiveProgression::where('exam_id', $exam->id)
            ->when(! $request->user()->isSuperAdmin(), fn ($query) => $query->where('adaptive_progressions.owner_key', app(AdaptiveRolloutService::class)->ownerKey($exam)))
            ->join('candidates', 'candidates.id', '=', 'adaptive_progressions.candidate_id')
            ->select('adaptive_progressions.id', 'adaptive_progressions.status', 'adaptive_progressions.stop_reason',
                'candidates.first_name', 'candidates.last_name', 'candidates.candidate_number')
            ->orderByDesc('adaptive_progressions.id')->paginate(25)->withQueryString();

        return Inertia::render('Results/AdaptiveIndex', [
            'exam' => ['id' => $exam->id, 'title' => $exam->title],
            'progressions' => $rows, 'interpretation' => AdaptiveReportService::INTERPRETATION,
            'rollout' => app(AdaptiveRolloutService::class)->status($exam),
        ]);
    }

    public function show(Request $request, AdaptiveProgression $progression, AdaptiveReportService $reports)
    {
        Gate::authorize('view', $progression);
        $report = $reports->report($progression);
        $this->audit($request, $progression, 'viewed');

        return Inertia::render('Results/Adaptive', ['report' => $report]);
    }

    public function export(Request $request, AdaptiveProgression $progression, AdaptiveReportService $reports)
    {
        Gate::authorize('view', $progression);
        $csv = $reports->csv($reports->report($progression));
        $this->audit($request, $progression, 'exported');

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="adaptive-progression-'.$progression->id.'.csv"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function audit(Request $request, AdaptiveProgression $progression, string $action): void
    {
        Exam::findOrFail($progression->exam_id)->auditLogs()->create([
            'actor_user_id' => $request->user()->id, 'actor_type' => 'user',
            'event_type' => 'adaptive_report_'.$action, 'description' => 'Adaptive diagnostic report '.$action.'.',
            'metadata' => ['progression_id' => $progression->id], 'occurred_at' => now(),
        ]);
    }
}
