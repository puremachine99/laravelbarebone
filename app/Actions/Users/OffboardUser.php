<?php

namespace App\Actions\Users;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OffboardUser
{
    /**
     * Cut a departing user's access without touching their history: the account is
     * suspended, live sessions and remember-me tokens are invalidated, and Gate::before
     * denies every ability. Roles are kept on purpose so their past work still resolves
     * in role-based lists (e.g. the Funding Officer filters).
     */
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->forceFill([
                'status' => UserStatus::Suspended,
                'remember_token' => Str::random(60),
            ])->save();

            DB::table('sessions')->where('user_id', $user->id)->delete();
        });
    }

    /**
     * Restore access for an employee who is active again (e.g. status corrected by mistake).
     */
    public function reactivate(User $user): void
    {
        if ($user->isSuspended()) {
            $user->forceFill(['status' => UserStatus::Active])->save();
        }
    }
}
