<?php

namespace App\Observers;

use App\Models\User;
use App\Services\Sso\SsoClientManager;

class UserObserver
{
    /**
     * A user who is suspended or moves department may lose access to connected apps (SSO): sign them out of those
     * apps right away instead of letting their tokens run out. Covers every path that changes these columns
     * (offboarding, the Master SDM sync, the department sync).
     */
    public function updated(User $user): void
    {
        if ($user->wasChanged(['status', 'department_id'])) {
            app(SsoClientManager::class)->revokeLostAccess($user);
        }
    }

    /**
     * A deleted user can no longer use any connected app.
     */
    public function deleted(User $user): void
    {
        app(SsoClientManager::class)->revokeLostAccess($user);
    }
}
