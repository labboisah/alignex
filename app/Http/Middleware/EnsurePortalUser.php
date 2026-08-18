<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isPortalUser() && $user->isActive()) {
            return $next($request);
        }

        if ($user && ! $user->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'This user account has been deactivated.',
            ]);
        }

        return Inertia::render('AccessDenied')
            ->toResponse($request)
            ->setStatusCode(403);
    }
}
