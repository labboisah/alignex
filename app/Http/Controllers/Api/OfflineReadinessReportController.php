<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OfflineReadinessReport;
use App\Services\OfflineActivationGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OfflineReadinessReportController extends Controller
{
    public function store(Request $request, OfflineActivationGuard $guard): JsonResponse
    {
        $validated = $request->validate([
            'report_id' => ['required', 'string', 'max:100'],
            'report_version' => ['required', 'string', 'max:80'],
            'drill_id' => ['required', 'string', 'max:100'],
            'drill_name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:ready,incomplete'],
            'content_pack_version' => ['required', 'string', 'max:100'],
            'expected_clients' => ['required', 'integer', 'min:0'],
            'connected_clients' => ['required', 'integer', 'min:0'],
            'completed_clients' => ['required', 'integer', 'min:0'],
            'failed_clients' => ['required', 'integer', 'min:0'],
            'total_questions' => ['required', 'integer', 'min:0'],
            'total_answers' => ['required', 'integer', 'min:0'],
            'total_submissions' => ['required', 'integer', 'min:0'],
            'event_count' => ['required', 'integer', 'min:0'],
            'started_at' => ['nullable', 'date'],
            'closed_at' => ['required', 'date'],
            'payload_hash' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
            'payload' => ['required', 'array'],
        ]);

        abort_unless($request->header('X-AlignEx-Device-Id'), 401, 'Device ID is required.');
        $activation = $guard->requireActive($request);

        $computedHash = hash('sha256', json_encode($validated['payload'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        abort_unless(hash_equals($computedHash, $validated['payload_hash']), 422, 'Readiness report payload checksum is invalid.');
        abort_unless(($validated['payload']['report_id'] ?? null) === $validated['report_id'], 422, 'Readiness report identity does not match its payload.');
        abort_unless(($validated['payload']['drill_id'] ?? null) === $validated['drill_id'], 422, 'Readiness drill identity does not match its payload.');

        $existing = OfflineReadinessReport::query()->where('drill_id', $validated['drill_id'])->first();
        if ($existing) {
            abort_unless(hash_equals($existing->payload_hash, $validated['payload_hash']), 409, 'This readiness drill already has a different report.');

            return response()->json(['report_id' => $existing->id, 'status' => 'duplicate', 'payload_hash' => $existing->payload_hash]);
        }

        $report = OfflineReadinessReport::query()->create([
            'id' => (string) Str::uuid(),
            'drill_id' => $validated['drill_id'],
            'activation_id' => $activation->id,
            'organization_id' => $activation->organization_id,
            'cbt_center_id' => $activation->cbt_center_id,
            'report_version' => $validated['report_version'],
            'drill_name' => $validated['drill_name'],
            'readiness_status' => $validated['status'],
            'content_pack_version' => $validated['content_pack_version'],
            'expected_clients' => $validated['expected_clients'],
            'connected_clients' => $validated['connected_clients'],
            'completed_clients' => $validated['completed_clients'],
            'failed_clients' => $validated['failed_clients'],
            'total_questions' => $validated['total_questions'],
            'total_answers' => $validated['total_answers'],
            'total_submissions' => $validated['total_submissions'],
            'event_count' => $validated['event_count'],
            'payload_hash' => $validated['payload_hash'],
            'payload' => $validated['payload'],
            'started_at' => $validated['started_at'],
            'closed_at' => $validated['closed_at'],
            'uploaded_at' => now(),
        ]);

        return response()->json([
            'report_id' => $report->id,
            'status' => 'accepted',
            'payload_hash' => $report->payload_hash,
        ], 201);
    }
}
