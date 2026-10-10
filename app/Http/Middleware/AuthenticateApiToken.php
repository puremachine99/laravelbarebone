<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates the AI service with the single bearer token from `.env` (`AI_DATA_API_TOKEN`).
 * A missing, wrong or unconfigured token all return the same 401, and an empty configured token rejects everything.
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('ai_center.data_api.token');
        $provided = (string) $request->bearerToken();

        if ($expected === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Unauthenticated.'], 401, ['WWW-Authenticate' => 'Bearer']);
        }

        return $next($request);
    }
}
