<?php

namespace App\Actions\Users;

use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\UserInvitedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class InviteUser
{
    /**
     * Create a new invited user and email them a link to set their own password.
     *
     * @param  array<int, string>  $roles
     */
    public function handle(string $name, string $email, ?int $departmentId, array $roles, User $invitedBy): User
    {
        $user = DB::transaction(function () use ($name, $email, $departmentId, $roles, $invitedBy) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(Str::random(40)),
                'department_id' => $departmentId,
                'status' => UserStatus::Invited,
                'invited_by' => $invitedBy->id,
                'invited_at' => now(),
            ]);

            $user->syncRoles($roles);

            return $user;
        });

        $this->sendInvite($user);

        return $user;
    }

    /**
     * Invalidate any outstanding token and send a fresh invite email.
     */
    public function resend(User $user): void
    {
        Password::broker('invites')->deleteToken($user);

        $this->sendInvite($user);
    }

    private function sendInvite(User $user): void
    {
        $token = Password::broker('invites')->createToken($user);

        $user->notify(new UserInvitedNotification($token));
    }
}
