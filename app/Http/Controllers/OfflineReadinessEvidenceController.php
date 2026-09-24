<?php

namespace App\Http\Controllers;

use App\Models\OfflineReadinessReport;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class OfflineReadinessEvidenceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('OfflineReadinessReports/Index', [
            'reports' => OfflineReadinessReport::query()
                ->with(['organization:id,name', 'cbtCenter:id,name'])
                ->latest('closed_at')
                ->latest('created_at')
                ->get()
                ->map(fn (OfflineReadinessReport $report): array => [
                    'id' => $report->id,
                    'drill_id' => $report->drill_id,
                    'drill_name' => $report->drill_name,
                    'readiness_status' => $report->readiness_status,
                    'content_pack_version' => $report->content_pack_version,
                    'expected_clients' => $report->expected_clients,
                    'connected_clients' => $report->connected_clients,
                    'completed_clients' => $report->completed_clients,
                    'failed_clients' => $report->failed_clients,
                    'total_questions' => $report->total_questions,
                    'total_answers' => $report->total_answers,
                    'total_submissions' => $report->total_submissions,
                    'event_count' => $report->event_count,
                    'payload_hash' => $report->payload_hash,
                    'organization_name' => $report->organization?->name,
                    'center_name' => $report->cbtCenter?->name,
                    'started_at' => $report->started_at?->toISOString(),
                    'closed_at' => $report->closed_at?->toISOString(),
                    'uploaded_at' => $report->uploaded_at?->toISOString(),
                ]),
        ]);
    }

    public function download(OfflineReadinessReport $offlineReadinessReport): JsonResponse
    {
        $payload = $offlineReadinessReport->payload ?? [];
        $computedHash = hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        abort_unless(hash_equals($offlineReadinessReport->payload_hash, $computedHash), 409, 'The stored readiness evidence checksum is invalid.');

        return response()->json([
            'evidence_bundle_version' => 'alignex.readiness-evidence.v1',
            'exported_at' => now()->toISOString(),
            'report' => [
                'report_id' => $offlineReadinessReport->id,
                'drill_id' => $offlineReadinessReport->drill_id,
                'drill_name' => $offlineReadinessReport->drill_name,
                'status' => $offlineReadinessReport->readiness_status,
                'content_pack_version' => $offlineReadinessReport->content_pack_version,
                'expected_clients' => $offlineReadinessReport->expected_clients,
                'connected_clients' => $offlineReadinessReport->connected_clients,
                'completed_clients' => $offlineReadinessReport->completed_clients,
                'failed_clients' => $offlineReadinessReport->failed_clients,
                'total_questions' => $offlineReadinessReport->total_questions,
                'total_answers' => $offlineReadinessReport->total_answers,
                'total_submissions' => $offlineReadinessReport->total_submissions,
                'event_count' => $offlineReadinessReport->event_count,
                'started_at' => $offlineReadinessReport->started_at?->toISOString(),
                'closed_at' => $offlineReadinessReport->closed_at?->toISOString(),
                'uploaded_at' => $offlineReadinessReport->uploaded_at?->toISOString(),
                'payload_sha256' => $offlineReadinessReport->payload_hash,
            ],
            'evidence' => $payload,
        ], 200, [
            'Content-Disposition' => 'attachment; filename="readiness-evidence-'.$offlineReadinessReport->drill_id.'.json"',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}