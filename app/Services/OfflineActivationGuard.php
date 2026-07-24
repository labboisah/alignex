<?php

namespace App\Services;

use App\Models\OfflineActivationCode;
use App\Models\OfflineServerActivation;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

class OfflineActivationGuard
{
    public function requireActive(Request $request): OfflineServerActivation
    {
        $token = $this->syncToken($request);

        if (! $token) {
            $this->deny('Offline server activation token is required.', 401);
        }

        $activation = OfflineServerActivation::query()
            ->with('activationCode')
            ->where('license_key', $token)
            ->when($this->deviceId($request), fn ($query, string $deviceId) => $query->where('device_id', $deviceId))
            ->latest('activated_at')
            ->first();

        if (! $activation) {
            $this->deny('Offline server activation was not found for this device.', 401);
        }

        if ($activation->status !== 'activated') {
            $this->deny('This offline server device activation is not active. Reactivate it or restore the device from Manage Activation.', 403);
        }

        if ($activation->expires_at && $activation->expires_at->isPast()) {
            $this->deny('This offline server device activation has expired.', 410);
        }

        $activationCode = $activation->activationCode;

        if (! $activationCode || $activationCode->status !== OfflineActivationCode::STATUS_ACTIVE) {
            $this->deny('This offline server activation code is not active.', 403);
        }

        if ($activationCode->expires_at && $activationCode->expires_at->isPast()) {
            $this->deny('This offline server activation code has expired.', 410);
        }

        return $activation;
    }

    private function syncToken(Request $request): ?string
    {
        $token = $request->bearerToken()
            ?: $request->header('X-AlignEx-Sync-Token')
            ?: $request->header('X-AlignEx-License-Key');

        $token = is_string($token) ? trim($token) : '';

        return $token !== '' ? $token : null;
    }

    private function deviceId(Request $request): ?string
    {
        $deviceId = trim((string) $request->header('X-AlignEx-Device-Id'));

        return $deviceId !== '' ? $deviceId : null;
    }

    private function deny(string $message, int $status): never
    {
        throw new HttpResponseException(response()->json(['message' => $message], $status));
    }
}
