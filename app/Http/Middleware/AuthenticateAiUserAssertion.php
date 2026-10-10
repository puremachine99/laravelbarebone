<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AiData\UserAssertion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the user a data API request is for, from the signed X-AI-User-Assertion header (see UserAssertion).
 * Runs after `api.token`. 401 for a missing, invalid or expired assertion; 403 when the user no longer exists, is not
 * active, or does not own the AI Center conversation in the assertion. Department or role claims sent by the AI
 * service are never trusted: everything downstream reads the user from our database.
 */
class AuthenticateAiUserAssertion
{
    public function __construct(private UserAssertion $assertions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $claims = $this->assertions->verify((string) $request->header('X-AI-User-Assertion'));

        if ($claims === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = User::find($claims['user_id']);

        $allowed = $user !== null
            && $user->isActive()
            && $user->aiConversations()->whereKey($claims['conversation_id'])->exists();

        if (! $allowed) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
