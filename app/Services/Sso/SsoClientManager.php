<?php

namespace App\Services\Sso;

use App\Models\OAuthClient;
use App\Models\OAuthClientAudit;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

/**
 * Every write the /tech/sso page makes to an OAuth client goes through here, so the encrypted secret copy
 * and the audit trail always stay in sync with Passport's own tables.
 */
class SsoClientManager
{
    /**
     * Register an authorization-code app. Confidential apps (a backend such as a Laravel/Filament app) get a
     * secret; public apps (SPA/mobile) must use PKCE instead.
     *
     * @param  array{name: string, description?: ?string, redirect_uris: array<int, string>, confidential: bool, scopes: array<int, string>, skip_authorization: bool, allowed_roles: array<int, string>, allowed_department_ids: array<int, int>}  $data
     */
    public function create(array $data): OAuthClient
    {
        return DB::transaction(function () use ($data) {
            $secret = $data['confidential'] ? Str::random(40) : null;

            /** @var OAuthClient $client */
            $client = OAuthClient::query()->forceCreate([
                ...$this->settings($data),
                'secret' => $secret,
                'grant_types' => ['authorization_code', 'refresh_token'],
                'revoked' => false,
            ]);

            if ($secret !== null) {
                $client->storedSecret()->create(['secret' => $secret]);
            }

            OAuthClientAudit::record($client, 'created');

            return $client;
        });
    }

    /**
     * @param  array{name: string, description?: ?string, redirect_uris: array<int, string>, scopes: array<int, string>, skip_authorization: bool, allowed_roles: array<int, string>, allowed_department_ids: array<int, int>}  $data
     */
    public function update(OAuthClient $client, array $data): void
    {
        $client->forceFill($this->settings($data))->save();

        OAuthClientAudit::record($client, 'updated');
    }

    /**
     * Issue a new secret. The old one stops working immediately, so the connected app must be updated.
     */
    public function regenerateSecret(OAuthClient $client): string
    {
        abort_unless($client->confidential(), 422, 'Aplikasi public (PKCE) tidak memakai secret.');

        $secret = Str::random(40);

        DB::transaction(function () use ($client, $secret) {
            $client->forceFill(['secret' => $secret])->save();
            $client->storedSecret()->updateOrCreate([], ['secret' => $secret]);
        });

        OAuthClientAudit::record($client, 'secret_regenerated');

        return $secret;
    }

    /**
     * The plain secret, or null when there is no readable copy (public app, or the copy was encrypted with an
     * older APP_KEY). Every reveal is audited.
     */
    public function revealSecret(OAuthClient $client): ?string
    {
        try {
            $secret = $client->storedSecret?->secret;
        } catch (DecryptException) {
            $secret = null;
        }

        if ($secret !== null) {
            OAuthClientAudit::record($client, 'secret_revealed');
        }

        return $secret;
    }

    /**
     * Sign everyone (or one user) out of the app: revokes their access and refresh tokens.
     */
    public function revokeTokens(OAuthClient $client, ?int $userId = null): int
    {
        $tokenIds = Passport::token()->newQuery()
            ->where('client_id', $client->getKey())
            ->where('revoked', false)
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->pluck('id');

        Passport::token()->newQuery()->whereKey($tokenIds)->update(['revoked' => true]);
        Passport::refreshToken()->newQuery()->whereIn('access_token_id', $tokenIds)->update(['revoked' => true]);

        OAuthClientAudit::record($client, $userId ? 'user_tokens_revoked' : 'tokens_revoked');

        return $tokenIds->count();
    }

    /**
     * Disable (revoke) or re-enable the app. A disabled app can't start new logins and its tokens are revoked.
     */
    public function setEnabled(OAuthClient $client, bool $enabled): void
    {
        $client->forceFill(['revoked' => ! $enabled])->save();

        if (! $enabled) {
            $this->revokeTokens($client);
        }

        OAuthClientAudit::record($client, $enabled ? 'enabled' : 'disabled');
    }

    /**
     * Permanently remove the app together with its tokens, codes and stored secret.
     */
    public function delete(OAuthClient $client): void
    {
        DB::transaction(function () use ($client) {
            $tokenIds = Passport::token()->newQuery()->where('client_id', $client->getKey())->pluck('id');

            Passport::refreshToken()->newQuery()->whereIn('access_token_id', $tokenIds)->delete();
            Passport::token()->newQuery()->whereKey($tokenIds)->delete();
            Passport::authCode()->newQuery()->where('client_id', $client->getKey())->delete();

            OAuthClientAudit::record($client, 'deleted');

            $client->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function settings(array $data): array
    {
        $scopes = array_values(array_intersect(array_keys(OAuthClient::SCOPES), $data['scopes']));

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'redirect_uris' => array_values($data['redirect_uris']),
            // "profile" is the default scope, so it is always allowed.
            'scopes' => array_values(array_unique(['profile', ...$scopes])),
            'skip_authorization' => $data['skip_authorization'],
            'allowed_roles' => array_values($data['allowed_roles']) ?: null,
            'allowed_department_ids' => array_values(array_map('intval', $data['allowed_department_ids'])) ?: null,
        ];
    }
}
