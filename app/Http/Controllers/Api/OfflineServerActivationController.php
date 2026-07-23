<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OfflineActivationCode;
use App\Models\OfflineServerActivation;
use App\Models\User;
use App\Services\PlanFeatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OfflineServerActivationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'activation_code' => ['required', 'string', 'max:120'],
            'device_id' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'string', 'max:255'],
            'admin_password' => ['required', 'string', 'max:255'],
            'center_name' => ['nullable', 'string', 'max:255'],
        ]);

        $adminEmail = trim($validated['admin_email']);
        $admin = User::query()->where('email', $adminEmail)->first();

        if (! $admin || ! $admin->isPortalUser() || ! Hash::check($validated['admin_password'], $admin->password)) {
            return response()->json(['message' => 'Invalid platform admin email or password.'], 401);
        }

        $activationCode = $this->findActivationCode($validated['activation_code']);

        if (! $activationCode) {
            throw ValidationException::withMessages([
                'activation_code' => 'Activation code is invalid.',
            ]);
        }

        if ($activationCode->status !== OfflineActivationCode::STATUS_ACTIVE) {
            return response()->json(['message' => 'Activation code is not active.'], 403);
        }

        if ($activationCode->expires_at && $activationCode->expires_at->isPast()) {
            return response()->json(['message' => 'Activation code has expired.'], 410);
        }

        if (! $this->adminCanUseActivationCode($admin, $activationCode)) {
            return response()->json(['message' => 'This admin is not allowed to activate this offline server.'], 403);
        }

        if ($activationCode->activations()->where('status', 'activated')->where('device_id', '!=', $validated['device_id'])->exists()) {
            return response()->json(['message' => 'This activation code has already been used on another device. Reset it from the portal before activating a different device.'], 409);
        }

        $existingDeviceActivation = $activationCode->activations()
            ->where('status', 'activated')
            ->where('device_id', $validated['device_id'])
            ->latest()
            ->first();

        if (! $existingDeviceActivation && $activationCode->activation_count >= $activationCode->max_activations) {
            return response()->json(['message' => 'Activation code has reached its activation limit.'], 409);
        }

        $activation = DB::transaction(function () use ($activationCode, $validated, $request, $existingDeviceActivation, $adminEmail): OfflineServerActivation {
            $now = now();
            $licenseExpiresAt = $activationCode->license_expires_at ?: $now->copy()->addYear();
            $licenseKey = $existingDeviceActivation?->license_key ?: 'offline-'.Str::lower(Str::random(48));

            /** @var OfflineServerActivation $activation */
            $activation = OfflineServerActivation::query()->updateOrCreate(
                [
                    'offline_activation_code_id' => $activationCode->id,
                    'device_id' => $validated['device_id'],
                ],
                [
                    'organization_id' => $activationCode->organization_id,
                    'cbt_center_id' => $activationCode->cbt_center_id,
                    'admin_email' => $adminEmail,
                    'center_name' => $validated['center_name'] ?? $activationCode->cbtCenter?->name,
                    'license_key' => $licenseKey,
                    'status' => 'activated',
                    'activated_at' => $now,
                    'expires_at' => $licenseExpiresAt,
                    'request_payload' => [
                        ...$request->only(['activation_code', 'device_id', 'center_name']),
                        'admin_email' => $adminEmail,
                    ],
                ],
            );

            $activationCode->forceFill([
                'activation_count' => $existingDeviceActivation ? $activationCode->activation_count : $activationCode->activation_count + 1,
                'last_activated_at' => $now,
                'last_device_id' => $validated['device_id'],
                'last_admin_email' => $adminEmail,
            ])->save();

            return $activation;
        });

        $activation->load(['organization.pricingPlan', 'cbtCenter.pricingPlan', 'cbtCenter.organization.pricingPlan']);
        $planOwner = $activation->cbtCenter ?? $activation->organization;
        $planFeatures = app(PlanFeatureService::class);

        return response()->json([
            'license_key' => $activation->license_key,
            'organization_name' => $activation->organization?->name ?? 'AlignEx Organization',
            'center_name' => $activation->cbtCenter?->name ?? $activation->center_name ?? 'Offline Center',
            'center_id' => $activation->cbt_center_id ? (string) $activation->cbt_center_id : null,
            'portal_url' => $request->root(),
            'sync_token' => null,
            'plan' => $planFeatures->planSummaryForOwner($planOwner),
            'plan_features' => $planFeatures->featuresForOwner($planOwner),
            'admin_name' => $admin->name,
            'device_id' => $validated['device_id'],
            'same_device' => $existingDeviceActivation !== null,
            'activated_at' => $activation->activated_at?->toISOString(),
            'expires_at' => $activation->expires_at?->toISOString(),
            'status' => 'activated',
        ]);
    }

    private function findActivationCode(string $plainCode): ?OfflineActivationCode
    {
        return OfflineActivationCode::query()
            ->with(['organization', 'cbtCenter'])
            ->where('status', OfflineActivationCode::STATUS_ACTIVE)
            ->get()
            ->first(fn (OfflineActivationCode $activationCode): bool => Hash::check($plainCode, $activationCode->code_hash));
    }

    private function adminCanUseActivationCode(User $admin, OfflineActivationCode $activationCode): bool
    {
        if ($admin->isSuperAdmin()) {
            return true;
        }

        if ($activationCode->created_by_user_id === $admin->id) {
            return true;
        }

        if ($activationCode->organization_id && ! $admin->canAccessOrganization($activationCode->organization_id)) {
            return false;
        }

        if ($activationCode->cbt_center_id && ! $admin->canAccessCbtCenter($activationCode->cbt_center_id)) {
            return false;
        }

        return $admin->hasPermission('downloadOfflineServer') || $admin->hasPermission('manageExams') || $admin->hasPermission('manageCenters');
    }
}
