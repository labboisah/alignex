<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEntityContextAccess
{
    public function handle(Request $request, Closure $next, string $contextType, ?string $contextId = null): Response
    {
        $user = $request->user();
        abort_unless($user, 403);

        $id = $contextId ? $request->route($contextId, $request->input($contextId)) : null;
        $id = is_object($id) && isset($id->id) ? $id->id : $id;

        $allowed = match ($contextType) {
            'organization' => $user->canAccessOrganization($id ?? $user->organization_id),
            'secondary_school' => $user->canAccessSecondarySchool($id ?? $user->secondary_school_id ?? $user->school_id),
            'professional_school' => $user->canAccessProfessionalSchool($id ?? $user->professional_school_id),
            'cbt_center' => $user->canAccessCbtCenter($id ?? $user->cbt_center_id ?? $user->center_id),
            default => false,
        };

        abort_unless($allowed, 403);

        return $next($request);
    }
}
