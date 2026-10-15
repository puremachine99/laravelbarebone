<?php

namespace App\Http\Middleware;

use App\Models\OAuthClient;
use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\Exceptions\AuthenticationException;
use Laravel\Passport\Http\Middleware\EnsureClientIsResourceOwner;
use Symfony\Component\HttpFoundation\Response;

/**
 * Machine-to-machine calls from a connected app (SSO): accepts only a client-credentials token, never a token
 * issued on behalf of a user, and exposes the calling app as the "oauth_client" request attribute.
 */
class AuthenticateOAuthClient extends EnsureClientIsResourceOwner
{
    public function handle(Request $request, Closure $next, string ...$params): Response
    {
        $token = $this->validateToken($request);

        $this->validate($token, ...$params);

        $client = OAuthClient::query()->find($token->oauth_client_id);

        if (! $client || $client->revoked) {
            throw new AuthenticationException;
        }

        $request->attributes->set('oauth_client', $client);

        return $next($request);
    }
}
