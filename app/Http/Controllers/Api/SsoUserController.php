<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OAuthClient;
use App\Models\User;
use App\Services\Sso\SsoUserProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Who is this?" for connected apps (SSO): returns the token owner's profile, limited to the scopes the token
 * was granted. Tokens of users who are no longer active, or no longer allowed into the app, are refused.
 */
class SsoUserController extends Controller
{
    public function __invoke(Request $request, SsoUserProfile $profile): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $client = OAuthClient::query()->find($user->token()?->client_id);

        if (! $client || ! $client->allowsUser($user)) {
            return response()->json(['message' => 'Akun ini tidak memiliki akses ke aplikasi ini.'], 403);
        }

        if (! $client->last_used_at || $client->last_used_at->lt(now()->subMinute())) {
            $client->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        $grantedScopes = array_values(array_filter(array_keys(OAuthClient::SCOPES), fn (string $scope) => $user->tokenCan($scope)));

        return response()->json($profile->toArray($user, $grantedScopes));
    }
}
