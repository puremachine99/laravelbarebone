<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for Sentry (/tech/sentry) and the old /dev/* URLs: anyone without the `tech-sentry.view` permission
 * (the Technology module's "Sentry" sub-menu in Role Management, or implicitly a Super Admin) gets a plain
 * 404 instead of a redirect-to-login or 403, so the existence of these routes isn't revealed to unauthorized
 * visitors.
 */
class RequireSuperAdminOr404
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isActive() || ! $user->can('tech-sentry.view')) {
            abort(404);
        }

        return $next($request);
    }
}
