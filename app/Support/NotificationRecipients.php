<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Partnership and Investors/Funding are separate departments with their own
 * escalation model — they are NOT interchangeable, so each module gets its
 * own recipient resolver rather than one generic "managers" concept.
 */
class NotificationRecipients
{
    /**
     * Partnership's manager sign-off chain: users who hold the cross-cutting
     * `verify-manager` permission (or Super Admin) AND actually have access
     * to the Partnership module. The frontline role there is "PMO".
     *
     * @return Collection<int, User>
     */
    public static function partnershipManagers(): Collection
    {
        return User::with('roles.permissions', 'permissions')
            ->get()
            ->filter(fn (User $user) => self::hasPermission($user, 'access-partnership')
                && (
                    $user->email === 'supernakama@smartid.co.id'
                    || $user->roles->contains('name', 'Super Admin')
                    || self::hasPermission($user, 'verify-manager')
                ))
            ->values();
    }

    /**
     * Investors/Funding has no manager sign-off concept — escalation is the
     * "Investor Admin" role (same set as `isAdminFo` in funnel-index.blade.php).
     *
     * @return Collection<int, User>
     */
    public static function investorAdmins(): Collection
    {
        return User::with('roles')
            ->get()
            ->filter(fn (User $user) => $user->roles->contains('name', 'Investor Admin')
                || $user->roles->contains('name', 'Super Admin'))
            ->values();
    }

    private static function hasPermission(User $user, string $permission): bool
    {
        return $user->permissions->contains('name', $permission)
            || $user->roles->flatMap->permissions->contains('name', $permission);
    }
}
