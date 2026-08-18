<?php

namespace App\Http\Controllers\Auth;

use App\Concerns\PasswordValidationRules;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class SetPasswordController extends Controller
{
    use PasswordValidationRules;

    /**
     * Show the form an invited user uses to set their own password.
     */
    public function create(Request $request, string $token): View|RedirectResponse
    {
        $user = $this->findInvitedUser($request->query('email'));

        if (! $user || ! Password::broker('invites')->tokenExists($user, $token)) {
            return redirect()->route('login')->with('status', 'Link undangan tidak valid atau sudah kedaluwarsa. Hubungi admin untuk mengirim ulang undangan.');
        }

        return view('pages.auth.set-password', [
            'token' => $token,
            'email' => $user->email,
        ]);
    }

    /**
     * Activate the invited user's account with the password they chose.
     */
    public function store(Request $request, string $token): RedirectResponse
    {
        Validator::make($request->all(), [
            'email' => ['required', 'string', 'email'],
            'password' => $this->passwordRules(),
        ])->validate();

        $user = $this->findInvitedUser($request->input('email'));

        if (! $user || ! Password::broker('invites')->tokenExists($user, $token)) {
            return back()->withErrors([
                'email' => 'Link undangan tidak valid atau sudah kedaluwarsa. Hubungi admin untuk mengirim ulang undangan.',
            ]);
        }

        $user->forceFill([
            'password' => $request->input('password'),
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ])->save();

        Password::broker('invites')->deleteToken($user);

        return redirect()->route('login')->with('status', 'Password berhasil dibuat. Silakan login.');
    }

    private function findInvitedUser(?string $email): ?User
    {
        if (! $email) {
            return null;
        }

        return User::where('email', $email)->where('status', UserStatus::Invited)->first();
    }
}
