{{--
    Step-by-step "Login with SmartID" setup for a client Laravel 11+ app (optionally Filament), no extra packages.
    Shared by the credentials modal in /tech/sso (real values) and /tech/documentation (placeholders).

    @var string $host           SmartID base URL
    @var string $clientId
    @var string $clientSecret   real secret or a placeholder
    @var string $redirect       callback URL registered for the app
    @var string $scopes         space-separated scopes
    @var bool   $confidential   false for public (PKCE) apps — not covered by this guide
--}}
@php
    $controller = <<<'PHP'
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * "Login with SmartID": sends the user to SmartID, then signs them in here with the profile SmartID returns.
 */
class SmartIdController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $request->session()->put('smartid_state', $state = Str::random(40));

        return redirect(config('services.smartid.host').'/oauth/authorize?'.http_build_query([
            'client_id' => config('services.smartid.client_id'),
            'redirect_uri' => config('services.smartid.redirect'),
            'response_type' => 'code',
            'scope' => config('services.smartid.scopes'),
            'state' => $state,
        ]));
    }

    public function callback(Request $request): RedirectResponse
    {
        $state = $request->session()->pull('smartid_state');

        abort_unless($state && hash_equals($state, (string) $request->query('state')), 403, 'Sesi login SmartID kedaluwarsa, silakan coba lagi.');

        if ($request->filled('error')) {
            return redirect('/')->with('error', 'Login dengan SmartID dibatalkan.');
        }

        // 1. Tukar "code" dengan access token (server ke server, secret tidak pernah ke browser).
        $token = Http::asForm()->acceptJson()->post(config('services.smartid.host').'/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => config('services.smartid.client_id'),
            'client_secret' => config('services.smartid.client_secret'),
            'redirect_uri' => config('services.smartid.redirect'),
            'code' => $request->query('code'),
        ])->throw()->json();

        // 2. Ambil profil user dari SmartID.
        $profile = Http::withToken($token['access_token'])->acceptJson()
            ->get(config('services.smartid.host').'/api/me')
            ->throw()
            ->json();

        // 3. Cari user lokal berdasarkan email (dibuat kalau belum ada), lalu login.
        $user = User::firstOrCreate(
            ['email' => $profile['email']],
            ['name' => $profile['name'], 'password' => Str::password(32)],
        );

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended('/');
    }
}
PHP;

    $steps = [
        [
            'title' => 'Isi .env aplikasi client',
            'file' => '.env',
            'note' => $confidential ? 'Secret hanya disimpan di server aplikasi client, jangan di-commit.' : 'Aplikasi public tidak memakai secret; panduan ini untuk aplikasi Laravel (confidential).',
            'code' => "SMARTID_HOST={$host}\nSMARTID_CLIENT_ID={$clientId}\nSMARTID_CLIENT_SECRET={$clientSecret}\nSMARTID_REDIRECT_URI={$redirect}\nSMARTID_SCOPES=\"{$scopes}\"\n\n# Wajib kalau client & SmartID sama-sama jalan di localhost: cookie session tidak dipisah per port.\nSESSION_COOKIE=client_app_session",
        ],
        [
            'title' => 'Tambahkan konfigurasi',
            'file' => 'config/services.php',
            'note' => 'Tambahkan di dalam array return yang sudah ada.',
            'code' => <<<'PHP'
'smartid' => [
    'host' => rtrim((string) env('SMARTID_HOST'), '/'),
    'client_id' => env('SMARTID_CLIENT_ID'),
    'client_secret' => env('SMARTID_CLIENT_SECRET'),
    'redirect' => env('SMARTID_REDIRECT_URI'),
    'scopes' => env('SMARTID_SCOPES', 'profile email'),
],
PHP,
        ],
        [
            'title' => 'Buat controller',
            'file' => 'app/Http/Controllers/Auth/SmartIdController.php',
            'note' => 'Tidak perlu package tambahan, cukup HTTP client bawaan Laravel. Ubah langkah 3 kalau user harus sudah ada lebih dulu (jangan dibuat otomatis).',
            'code' => $controller,
        ],
        [
            'title' => 'Daftarkan route',
            'file' => 'routes/web.php',
            'note' => 'Path callback harus sama persis dengan Redirect URI yang terdaftar di SmartID.',
            'code' => <<<'PHP'
use App\Http\Controllers\Auth\SmartIdController;

Route::get('/auth/smartid/redirect', [SmartIdController::class, 'redirect'])->name('smartid.redirect');
Route::get('/auth/smartid/callback', [SmartIdController::class, 'callback'])->name('smartid.callback');
PHP,
        ],
        [
            'title' => 'Pasang tombol "Login with SmartID"',
            'file' => 'resources/views/auth/login.blade.php (atau view login Anda)',
            'note' => 'Untuk aplikasi tanpa Filament. Pakai Filament? Lihat langkah berikutnya.',
            'code' => '<a href="{{ route(\'smartid.redirect\') }}" class="btn">Login with SmartID</a>',
        ],
        [
            'title' => 'Filament: tombol di halaman login panel',
            'file' => 'app/Providers/Filament/AdminPanelProvider.php',
            'note' => 'Lalu buat view resources/views/filament/smartid-login.blade.php berisi tombol di bawah. Ganti redirect()->intended(\'/\') di controller menjadi redirect()->intended(filament()->getUrl()). Pastikan model User mengizinkan akses panel (FilamentUser::canAccessPanel).',
            'code' => <<<'PHP'
use Filament\View\PanelsRenderHook;

// di dalam ->panel($panel) ... :
->renderHook(
    PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
    fn () => view('filament.smartid-login'),
)

// resources/views/filament/smartid-login.blade.php
<x-filament::button tag="a" :href="route('smartid.redirect')" color="gray" class="w-full">
    Login with SmartID
</x-filament::button>
PHP,
        ],
        [
            'title' => 'Muat ulang config & coba',
            'file' => 'terminal',
            'note' => 'Buka halaman login aplikasi client lalu klik "Login with SmartID". Login pertama akan menampilkan layar "Izinkan" (kecuali consent dilewati).',
            'code' => 'php artisan config:clear',
        ],
    ];
@endphp

<ol class="space-y-4">
    @foreach ($steps as $step)
        <li class="flex gap-3" x-data="{ copied: false }">
            <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-content">{{ $loop->iteration }}</span>
            <div class="min-w-0 grow space-y-1.5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm font-semibold">{{ $step['title'] }}</p>
                    <button type="button" class="btn btn-ghost btn-xs"
                        x-on:click="navigator.clipboard.writeText($refs.code.innerText); copied = true; setTimeout(() => copied = false, 1500)">
                        <span x-show="! copied">Salin</span><span x-show="copied" x-cloak>✓ Tersalin</span>
                    </button>
                </div>
                <p class="font-mono text-xs text-base-content/60">{{ $step['file'] }}</p>
                <pre class="max-h-96 overflow-auto rounded-box bg-[#0d1117] p-3 text-xs text-[#c9d1d9]"><code x-ref="code">{{ $step['code'] }}</code></pre>
                <p class="text-xs text-base-content/60">{{ $step['note'] }}</p>
            </div>
        </li>
    @endforeach
</ol>
