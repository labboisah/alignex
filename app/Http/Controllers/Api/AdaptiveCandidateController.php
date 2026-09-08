<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdaptiveNextLevelRequest;
use App\Services\AdaptiveLifecycleService;
use App\Services\CandidateExamSessionService;
use Illuminate\Http\JsonResponse;

class AdaptiveCandidateController extends Controller
{
    public function nextLevel(AdaptiveNextLevelRequest $request, CandidateExamSessionService $session, AdaptiveLifecycleService $lifecycle): JsonResponse
    {
        $attempt = $session->attemptFromRequest($request);
        abort_unless($lifecycle->handles($attempt), 422, 'This attempt does not use adaptive progression.');

        return response()->json($lifecycle->execute($attempt, 'next-level', $request->validated()));
    }
}
