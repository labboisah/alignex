<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OfflineReadinessPackage;
use App\Services\OfflineActivationGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfflineReadinessPackageController extends Controller
{
    public function __construct(private readonly OfflineActivationGuard $activationGuard) {}

    public function show(Request $request, string $code): JsonResponse
    {
        $activation = $this->activationGuard->requireActive($request);
        $package = OfflineReadinessPackage::query()
            ->where('code', strtolower(trim($code)))
            ->latest('id')
            ->first();

        if (! $package) {
            return response()->json(['message' => 'The Autoboot readiness package code was not found. Do not use an official exam code.'], 404);
        }

        if ($package->status !== OfflineReadinessPackage::STATUS_ACTIVE) {
            return response()->json(['message' => "Autoboot package {$package->code} is {$package->status}. Activate it in the platform before importing it into the Center Server."], 409);
        }

        $payloadJson = json_encode($package->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $payloadChecksum = hash('sha256', $payloadJson);

        return response()->json([
            'package' => [
                'code' => $package->code,
                'version' => $package->version,
                'capacity_profile' => $package->capacity_profile,
                'candidate_count' => $package->candidate_count,
                'backup_percent' => (float) $package->backup_percent,
                'autoboot_target_clients' => $package->autoboot_target_clients,
                'question_count' => $package->question_count,
                'subject_count' => $package->subject_count,
                'checksum_sha256' => $payloadChecksum,
                'payload_json' => $payloadJson,
                'payload' => $package->payload,
            ],
            'activation_id' => (string) $activation->id,
        ]);
    }
}
