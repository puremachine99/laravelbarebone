<?php

namespace App\Models;

use App\Enums\UserStatus;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Passport\Client;
use Laravel\Passport\Scope;

/**
 * Passport client with the SSO settings managed from /tech/sso: per-app scopes, consent skipping,
 * role/department restrictions and a revealable (encrypted) copy of the secret.
 */
class OAuthClient extends Client
{
    /**
     * Scopes an app can be granted, with the label shown on the consent screen and in /tech/sso.
     *
     * @var array<string, string>
     */
    public const SCOPES = [
        'profile' => 'Nama, username & foto profil',
        'email' => 'Alamat email',
        'department' => 'Departemen & jabatan',
        'roles' => 'Role & permission',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'skip_authorization' => 'bool',
            'allowed_roles' => 'array',
            'allowed_department_ids' => 'array',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * The encrypted copy of the client secret (confidential clients only).
     *
     * @return HasOne<OAuthClientSecret, $this>
     */
    public function storedSecret(): HasOne
    {
        return $this->hasOne(OAuthClientSecret::class, 'client_id');
    }

    /**
     * Trusted internal apps can skip the "Izinkan akses?" screen.
     *
     * @param  Scope[]  $scopes
     */
    public function skipsAuthorization(Authenticatable $user, array $scopes): bool
    {
        return $this->skip_authorization;
    }

    /**
     * Whether the user may sign in to this app: they must be active and, when the app is restricted,
     * hold one of the allowed roles or belong to one of the allowed departments.
     */
    public function allowsUser(User $user): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        $roles = $this->allowed_roles ?? [];
        $departmentIds = array_map('intval', $this->allowed_department_ids ?? []);

        if ($roles === [] && $departmentIds === []) {
            return true;
        }

        return ($roles !== [] && $user->hasAnyRole($roles))
            || ($departmentIds !== [] && in_array((int) $user->department_id, $departmentIds, true));
    }

    /**
     * Everyone who may currently sign in to this app: the query counterpart of allowsUser().
     *
     * @return Builder<User>
     */
    public function allowedUsers(): Builder
    {
        $roles = $this->allowed_roles ?? [];
        $departmentIds = array_map('intval', $this->allowed_department_ids ?? []);

        return User::query()
            ->where('status', UserStatus::Active)
            ->when($roles !== [] || $departmentIds !== [], fn (Builder $query) => $query->where(function (Builder $query) use ($roles, $departmentIds) {
                $query
                    ->when($roles !== [], fn (Builder $query) => $query->orWhereHas('roles', fn (Builder $query) => $query->whereIn('name', $roles)))
                    ->when($departmentIds !== [], fn (Builder $query) => $query->orWhereIn('department_id', $departmentIds));
            }));
    }
}
