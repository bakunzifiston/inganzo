<?php

namespace App\Http\Middleware;

use App\Enums\RoleName;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('role:artist') or 'role:partner_staff'.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();
        $required = RoleName::from($role);

        $allowed = match ($required) {
            RoleName::Artist => $user?->isArtist(),
            RoleName::PartnerStaff => $user?->isPartnerStaff(),
            default => $user?->hasRole($required),
        };

        abort_unless($allowed, 403);

        return $next($request);
    }
}
