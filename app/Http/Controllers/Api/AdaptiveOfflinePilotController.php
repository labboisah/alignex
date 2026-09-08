<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdaptiveOfflineSyncRequest;
use App\Models\AdaptiveOfflineLease;
use App\Models\AdaptiveOfflinePackage;
use App\Models\Exam;
use App\Models\User;
use App\Services\AdaptiveOfflinePilotService;
use App\Services\AdaptiveRolloutService;
use App\Services\OfflineActivationGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

class AdaptiveOfflinePilotController extends Controller
{
    public function package(Request $request, AdaptiveOfflinePackage $package, OfflineActivationGuard $guard, AdaptiveOfflinePilotService $service)
    {
        $activation = $guard->requireActive($request);
        $user = User::where('email', $request->header('X-AlignEx-Admin-Email'))->first();
        abort_unless($user && $user->isPortalUser() && Hash::check((string) $request->header('X-AlignEx-Admin-Password'), $user->password), 401);
        $exam = Exam::findOrFail($package->exam_id);
        Gate::forUser($user)->authorize('update', $exam);
        Gate::forUser($user)->authorize('viewAdaptiveReport', $exam);
        abort_unless(app(AdaptiveRolloutService::class)->ownerKey($exam) === $package->owner_key, 403);
        $exam->auditLogs()->create(['actor_user_id' => $user->id, 'actor_type' => 'user', 'event_type' => 'adaptive_offline_downloaded',
            'description' => 'Center retrieved its authenticated diagnostic package.', 'metadata' => ['package_id' => $package->id], 'occurred_at' => now()]);

        return response()->json($service->envelope($package, $activation));
    }

    public function sync(AdaptiveOfflineSyncRequest $request, AdaptiveOfflineLease $lease, OfflineActivationGuard $guard, AdaptiveOfflinePilotService $service)
    {
        return response()->json($service->sync($lease, $guard->requireActive($request), $request->validated()));
    }
}
