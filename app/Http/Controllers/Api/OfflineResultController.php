<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadOfflineResultRequest;
use App\Http\Resources\OfflineResultReceiptResource;
use App\Models\Exam;
use App\Models\User;
use App\Services\OfflineActivationGuard;
use App\Services\OfflineResultUploadService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class OfflineResultController extends Controller
{
    public function store(UploadOfflineResultRequest $request, OfflineActivationGuard $guard, OfflineResultUploadService $service)
    {
        abort_unless($request->header('X-AlignEx-Device-Id'), 401, 'Device ID is required.');
        $activation = $guard->requireActive($request);
        $user = User::where('email', trim((string) $request->header('X-AlignEx-Admin-Email')))->first();
        abort_unless($user && $user->isPortalUser() && Hash::check((string) $request->header('X-AlignEx-Admin-Password'), $user->password), 401, 'Portal admin credentials are invalid.');
        abort_unless(strcasecmp($activation->admin_email, $user->email) === 0, 403, 'Use the administrator who activated this server.');
        $exam = Exam::findOrFail($request->validated('exam_id'));
        Gate::forUser($user)->authorize('update', $exam);

        try {
            $receipt = $service->receive($request->validated(), $exam, $activation, $user);
        } catch (HttpExceptionInterface $error) {
            $exam->auditLogs()->create([
                'actor_user_id' => $user->id, 'actor_type' => 'user', 'event_type' => 'offline_result_upload_rejected',
                'description' => $error->getMessage(),
                'metadata' => ['upload_id' => $request->validated('upload_id'), 'activation_id' => $activation->id, 'http_status' => $error->getStatusCode()],
                'occurred_at' => now(),
            ]);
            throw $error;
        }

        return response()->json(['receipt' => new OfflineResultReceiptResource($receipt)]);
    }
}
