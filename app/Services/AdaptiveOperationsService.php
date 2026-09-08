<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AdaptiveOperationsService
{
    public function report(int $hours, ?string $owner = null): array
    {
        $since = now()->subHours($hours);
        $cohorts = DB::table('adaptive_progressions as p')
            ->join('adaptive_snapshots as s', 's.id', '=', 'p.snapshot_id')
            ->when($owner !== null, fn ($q) => $q->where('p.owner_key', $owner))
            ->where('p.created_at', '>=', $since)
            ->select('p.owner_key', 's.engine_version', 'p.status', 'p.stop_reason')
            ->selectRaw('COUNT(*) as progressions')
            ->groupBy('p.owner_key', 's.engine_version', 'p.status', 'p.stop_reason')
            ->orderBy('p.owner_key')->orderBy('s.engine_version')->orderBy('p.status')->orderBy('p.stop_reason')
            ->get();

        $stops = DB::table('adaptive_attempt_states as a')
            ->join('adaptive_snapshots as s', 's.id', '=', 'a.snapshot_id')
            ->where('a.updated_at', '>=', $since)->whereNotNull('a.stop_reason')
            ->when($owner !== null, fn ($q) => $q->where('s.owner_key', $owner))
            ->select('s.owner_key', 's.engine_version', 'a.stop_reason')
            ->selectRaw('COUNT(*) as attempts')
            ->groupBy('s.owner_key', 's.engine_version', 'a.stop_reason')
            ->orderBy('s.owner_key')->orderBy('s.engine_version')->orderBy('a.stop_reason')
            ->get();

        $shadow = DB::table('adaptive_engine_runs as r')
            ->join('adaptive_calibrations as c', 'c.id', '=', 'r.calibration_id')
            ->where('r.created_at', '>=', $since)
            ->when($owner !== null, fn ($q) => $q->where('c.owner_key', $owner))
            ->select('c.owner_key', 'c.id as calibration_id', 'c.version as calibration_version', 'r.status', 'r.error_code')
            ->selectRaw('COUNT(*) as evaluations, AVG(r.duration_ms) as mean_duration_ms, MAX(r.duration_ms) as max_duration_ms')
            ->groupBy('c.owner_key', 'c.id', 'c.version', 'r.status', 'r.error_code')
            ->orderBy('c.owner_key')->orderBy('c.id')->orderBy('r.status')->orderBy('r.error_code')
            ->get();

        $offline = DB::table('adaptive_offline_leases as l')
            ->join('adaptive_offline_packages as p', 'p.id', '=', 'l.package_id')
            ->where('l.updated_at', '>=', $since)
            ->when($owner !== null, fn ($q) => $q->where('p.owner_key', $owner))
            ->select('p.owner_key', 'l.status')->selectRaw('COUNT(*) as progressions')
            ->groupBy('p.owner_key', 'l.status')->orderBy('p.owner_key')->orderBy('l.status')->get();

        return [
            'generated_at' => now()->toISOString(), 'since' => $since->toISOString(),
            'owner_filter' => $owner, 'offline_adaptive_supported' => true, 'consequential_adaptive_approved' => false, 'offline_reconciliations' => $offline,
            'cohort_states' => $cohorts, 'recent_attempt_stops' => $stops,
            'shadow_evaluations' => $shadow,
            'unavailable_metrics' => ['live_selection_latency', 'duplicate_request_count'],
        ];
    }
}
