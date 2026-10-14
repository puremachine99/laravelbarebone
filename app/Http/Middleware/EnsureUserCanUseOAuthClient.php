<?php

namespace App\Http\Middleware;

use App\Models\OAuthClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs on Passport's routes (config/passport.php "middleware") and only acts on the authorize screen:
 * a signed-in user who isn't active, or isn't in the app's allowed roles/departments, never gets an
 * authorization code for that app. Guests pass through so Passport can send them to the login page first.
 */
class EnsureUserCanUseOAuthClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $request->routeIs('passport.authorizations.authorize')) {
            return $next($request);
        }

        $client = OAuthClient::query()->find((string) $request->query('client_id'));

        if ($client && ! $client->allowsUser($user)) {
            return response()->view('pages::auth.oauth-denied', ['client' => $client, 'user' => $user], 403);
        }

        return $next($request);
    }
}
