<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if ($request->user()?->hasPermission($permission)) {
            return $next($request);
        }

        return Inertia::render('AccessDenied')
            ->toResponse($request)
            ->setStatusCode(403);
    }
}
