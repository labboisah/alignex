<?php

namespace App\Http\Middleware;

use App\Services\PlanFeatureService;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if (! $user || ! app(PlanFeatureService::class)->hasFeature($user, $feature)) {
            throw new AuthorizationException('This feature is not available on your current plan.');
        }

        return $next($request);
    }
}
