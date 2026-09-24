<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Debug\ErrorLogController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hides dev-only routes from anyone who hasn't unlocked the debug panel
 * (see ErrorLogController::guard()): a 404 instead of a redirect to
 * /dev/login, so the route's existence isn't revealed to the unauthorized.
 */
class RequireDebugPanelSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->get(ErrorLogController::SESSION_KEY)) {
            abort(404);
        }

        return $next($request);
    }
}
