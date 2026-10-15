<?php

namespace App\Services\Sso;

use App\Models\User;

/**
 * The user data a connected app receives (from /api/me and /api/sso/members), limited to the given scopes.
 * Position-based access is decided by the app itself, so the "department" scope also carries the positions.
 */
class SsoUserProfile
{
    /**
     * Relations to eager load before building many profiles.
     *
     * @var array<int, string>
     */
    public const RELATIONS = [
        'department',
        'employee.positionStruktural',
        'employee.positionFungsional',
        'employee.positionTeknis',
        'roles',
    ];

    /**
     * @param  array<int, string>  $scopes
     * @return array<string, mixed>
     */
    public function toArray(User $user, array $scopes): array
    {
        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'avatar' => $user->avatarUrl(),
        ];

        if (in_array('email', $scopes, true)) {
            $data['email'] = $user->email;
            $data['email_verified'] = $user->email_verified_at !== null;
        }

        if (in_array('department', $scopes, true)) {
            $employee = $user->employee;

            $data['department'] = $user->department ? ['id' => $user->department->id, 'name' => $user->department->name] : null;
            $data['positions'] = [
                'struktural' => $employee?->positionStruktural?->only(['id', 'name']),
                'fungsional' => $employee?->positionFungsional?->only(['id', 'name']),
                'teknis' => $employee?->positionTeknis?->only(['id', 'name']),
            ];
        }

        if (in_array('roles', $scopes, true)) {
            $data['roles'] = $user->getRoleNames()->values();
            $data['permissions'] = $user->getAllPermissions()->pluck('name')->values();
        }

        return $data;
    }
}
