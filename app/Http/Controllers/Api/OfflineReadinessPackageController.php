<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OfflineReadinessPackage;
use App\Services\OfflineActivationGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class OfflineReadinessPackageController extends Controller
{
    public function __construct(private readonly OfflineActivationGuard $activationGuard) {}

    public function show(Request $request, string $code): JsonResponse
    {
        $activation = $this->activationGuard->requireActive($request);
        $email = trim((string) $request->header('X-AlignEx-Admin-Email'));
        $password = (string) $request->header('X-AlignEx-Admin-Password');
        $user = $activation->admin_email === $email
            ? \App\Models\User::query()->where('email', $email)->first()
            : null;

        if (! $user || ! Hash::check($password, $user->password)) {
            return response()->json(['message' => 'Offline sync admin credentials are invalid.'], 401);
        }

        $package = OfflineReadinessPackage::query()
            ->where('code', strtolower(trim($code)))
            ->where('status', OfflineReadinessPackage::STATUS_ACTIVE)
            ->latest('id')
            ->first();

        if (! $package) {
            return response()->json(['message' => 'The active readiness package was not found.'], 404);
        }

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
                'checksum_sha256' => $package->checksum_sha256,
                'payload' => $package->payload,
            ],
            'activation_id' => (string) $activation->id,
        ]);
    }
}
