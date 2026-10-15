<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OAuthClient;
use App\Models\User;
use App\Services\Sso\SsoUserProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Everyone who may currently sign in to the calling app, for its initial import and daily re-sync: a local user
 * missing from this list should be locked. Called with the app's own client-credentials token (no user involved);
 * the fields follow the scopes the app is allowed in /tech/sso.
 */
class SsoMemberController extends Controller
{
    public function __invoke(Request $request, SsoUserProfile $profile): JsonResponse
    {
        /** @var OAuthClient $client */
        $client = $request->attributes->get('oauth_client');

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $scopes = $client->scopes ?? array_keys(OAuthClient::SCOPES);

        $members = $client->allowedUsers()
            ->with(SsoUserProfile::RELATIONS)
            ->orderBy('id')
            ->paginate($validated['per_page'] ?? 100)
            ->withQueryString()
            ->through(fn (User $user) => $profile->toArray($user, $scopes));

        return response()->json($members);
    }
}
