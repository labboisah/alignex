<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isPortalUser()) {
            return $next($request);
        }

        abort(403, 'Candidates must use the separate exam login.');
    }
}
