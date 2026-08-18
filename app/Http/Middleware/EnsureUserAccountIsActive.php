<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Safety net beyond the login-time status check: a suspended (or still-invited) user
 * can otherwise reach an authenticated session via remember-me cookies or the two-factor
 * challenge, which resolve the guard directly and never go through Fortify::authenticateUsing.
 */
class EnsureUserAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            $message = match ($user->status) {
                UserStatus::Invited => 'Akun Anda belum diaktifkan. Silakan cek email undangan untuk membuat password.',
                UserStatus::Suspended => 'Akun Anda telah dinonaktifkan. Hubungi admin untuk informasi lebih lanjut.',
                default => 'Akun Anda tidak dapat mengakses aplikasi ini.',
            };

            auth()->guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', $message);
        }

        return $next($request);
    }
}
