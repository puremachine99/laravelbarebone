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
        $serviceUserEmail = (string) config('ai_center.data_api.service_user');

        if (! $request->headers->has('X-AI-User-Assertion') && $serviceUserEmail !== '') {
            return $this->asServiceUser($request, $next, $serviceUserEmail);
        }

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

    /**
     * Without an assertion the request runs as the configured service account (`AI_DATA_SERVICE_USER`), so the AI
     * service can query outside a chat. That account's own permissions decide its datasets and department scope.
     */
    private function asServiceUser(Request $request, Closure $next, string $email): Response
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! $user->isActive()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
