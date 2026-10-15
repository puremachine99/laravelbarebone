{{--
    Copy-paste setup of "Login with SmartID" + user sync for a client Laravel 11+ app (optionally Filament), no extra
    packages. Shared by the guide modal in /tech/sso (real values) and /tech/documentation (placeholders).

    @var string      $host            SmartID base URL
    @var string      $clientId
    @var string|null $clientSecret    the secret, or null while it is hidden (masked, with a reveal button)
    @var string|null $revealClientId  client to reveal the secret for (Livewire, /tech/sso only)
    @var string      $redirect        callback URL registered for the app
    @var string      $scopes          space-separated scopes
--}}
@php
    $callbackPath = parse_url($redirect, PHP_URL_PATH) ?: '/auth/smartid/callback';
    $secretHidden = $clientSecret === null;
    $isLocalhost = in_array(parse_url($redirect, PHP_URL_HOST), ['localhost', '127.0.0.1'], true);

    $env = fn (string $home) => implode("\n", array_filter([
        "SMARTID_HOST={$host}",
        "SMARTID_CLIENT_ID={$clientId}",
        'SMARTID_CLIENT_SECRET='.($clientSecret ?? str_repeat('•', 40)),
        "SMARTID_REDIRECT_URI={$redirect}",
        "SMARTID_SCOPES=\"{$scopes}\"",
        "SMARTID_HOME={$home}",
        // Client & SmartID on the same localhost share cookies across ports unless the names differ.
        $isLocalhost ? 'SESSION_COOKIE=client_app_session' : null,
    ]));

    $migration = <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('smartid_id')->nullable()->unique();
            $table->json('smartid_profile')->nullable();
            $table->timestamp('smartid_locked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['smartid_id']);
            $table->dropColumn(['smartid_id', 'smartid_profile', 'smartid_locked_at']);
        });
    }
};
PHP;

    $trait = <<<'PHP'
<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * User yang login lewat SmartID. Profil terakhir dari SmartID disimpan apa adanya di smartid_profile
 * (department, positions, roles — sesuai scope aplikasi).
 */
trait HasSmartId
{
    public function initializeHasSmartId(): void
    {
        $this->mergeCasts([
            'smartid_profile' => 'array',
            'smartid_locked_at' => 'datetime',
        ]);
    }

    /**
     * Aturan akses tambahan aplikasi ini. true = semua user yang diizinkan SmartID boleh masuk.
     * Contoh hanya jabatan partnership:
     * return str_contains(strtolower(json_encode($profile['positions'] ?? [])), 'partnership');
     */
    public static function smartIdAllows(array $profile): bool
    {
        return true;
    }

    /**
     * Buat / update user lokal dari profil SmartID. Akun lokal lama dengan email yang sama ikut ditautkan.
     */
    public static function syncFromSmartId(array $profile): static
    {
        $user = static::query()->where('smartid_id', $profile['id'])->first();

        if (! $user && ! empty($profile['email'])) {
            $user = static::query()->whereNull('smartid_id')->where('email', $profile['email'])->first();
        }

        $user ??= new static;

        if ($user->smartid_id === null) {
            // Login hanya lewat SmartID: password lokal diganti acak.
            $user->forceFill(['password' => Str::password(40)]);
        }

        $user->forceFill([
            'smartid_id' => $profile['id'],
            'name' => $profile['name'],
            'email' => $profile['email'] ?? $user->email ?? "smartid-{$profile['id']}@smartid.invalid",
            'smartid_profile' => $profile,
            'smartid_locked_at' => static::smartIdAllows($profile) ? null : ($user->smartid_locked_at ?? now()),
        ])->save();

        return $user;
    }

    public function lockSmartId(): void
    {
        if ($this->smartid_locked_at === null) {
            $this->forceFill(['smartid_locked_at' => now()])->save();
        }
    }

    public function isSmartIdLocked(): bool
    {
        return $this->smartid_locked_at !== null;
    }
}
PHP;

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
        abort_if($request->filled('error') || ! $request->filled('code'), 403, 'Login dengan SmartID dibatalkan.');

        $accessToken = Http::asForm()->acceptJson()->timeout(15)
            ->post(config('services.smartid.host').'/oauth/token', [
                'grant_type' => 'authorization_code',
                'client_id' => config('services.smartid.client_id'),
                'client_secret' => config('services.smartid.client_secret'),
                'redirect_uri' => config('services.smartid.redirect'),
                'code' => $request->query('code'),
            ])->throw()->json('access_token');

        $me = Http::withToken($accessToken)->acceptJson()->timeout(15)->get(config('services.smartid.host').'/api/me');

        abort_if($me->status() === 403, 403, 'Akun Anda tidak memiliki akses ke aplikasi ini.');

        $user = User::syncFromSmartId($me->throw()->json());

        abort_if($user->isSmartIdLocked(), 403, 'Akun Anda tidak memiliki akses ke aplikasi ini.');

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(config('services.smartid.home'));
    }
}
PHP;

    $middleware = <<<'PHP'
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * User yang dikunci (keluar dari SmartID / tidak lagi diizinkan) langsung ter-logout di request berikutnya.
 */
class EnsureSmartIdAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && method_exists($user, 'isSmartIdLocked') && $user->isSmartIdLocked()) {
            Auth::guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(403, 'Akses Anda ke aplikasi ini sudah dicabut.');
        }

        return $next($request);
    }
}
PHP;

    $command = <<<'PHP'
<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SmartIdSync extends Command
{
    protected $signature = 'smartid:sync';

    protected $description = 'Sinkronkan user dari SmartID; user yang tidak lagi terdaftar dikunci.';

    public function handle(): int
    {
        $host = config('services.smartid.host');

        $token = Http::asForm()->acceptJson()->timeout(15)->post("{$host}/oauth/token", [
            'grant_type' => 'client_credentials',
            'client_id' => config('services.smartid.client_id'),
            'client_secret' => config('services.smartid.client_secret'),
        ])->throw()->json('access_token');

        $seen = [];
        $url = "{$host}/api/sso/members?per_page=200";

        while ($url) {
            $page = Http::withToken($token)->acceptJson()->timeout(30)->get($url)->throw()->json();

            foreach ($page['data'] as $profile) {
                $seen[] = User::syncFromSmartId($profile)->getKey();
            }

            $url = $page['next_page_url'];
        }

        if ($seen === []) {
            $this->warn('Daftar dari SmartID kosong: tidak ada user yang dikunci. Cek pengaturan aplikasi di SmartID.');

            return self::SUCCESS;
        }

        $locked = 0;

        User::query()->whereNotNull('smartid_id')->whereNull('smartid_locked_at')->whereKeyNot($seen)
            ->each(function (User $user) use (&$locked) {
                $user->lockSmartId();
                $locked++;
            });

        $this->info(count($seen).' user tersinkron, '.$locked.' dikunci.');

        return self::SUCCESS;
    }
}
PHP;

    $services = <<<'PHP'
'smartid' => [
    'host' => rtrim((string) env('SMARTID_HOST'), '/'),
    'client_id' => env('SMARTID_CLIENT_ID'),
    'client_secret' => env('SMARTID_CLIENT_SECRET'),
    'redirect' => env('SMARTID_REDIRECT_URI'),
    'scopes' => env('SMARTID_SCOPES', 'profile'),
    'home' => env('SMARTID_HOME', '/'),
],
PHP;

    $routes = "Route::get('/auth/smartid/redirect', [\\App\\Http\\Controllers\\Auth\\SmartIdController::class, 'redirect'])->name('smartid.redirect');\n"
        ."Route::get('{$callbackPath}', [\\App\\Http\\Controllers\\Auth\\SmartIdController::class, 'callback'])->name('smartid.callback');";

    $filamentLoginHook = <<<'PHP'
->renderHook(
    \Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
    fn () => view('filament.smartid-login'),
)
PHP;

    $filamentAuthMiddleware = <<<'PHP'
->authMiddleware([
    \Filament\Http\Middleware\Authenticate::class,
    \App\Http\Middleware\EnsureSmartIdAccess::class,
])
PHP;

    $filamentButton = <<<'BLADE'
<x-filament::button tag="a" :href="route('smartid.redirect')" color="gray" class="w-full">
    Login with SmartID
</x-filament::button>
BLADE;

    $migrationFile = 'database/migrations/'.now()->format('Y_m_d').'_000000_add_smartid_columns_to_users_table.php';

    /*
     * Each step: [file, code, action note, stack, holds secret]. Stack "laravel" or "filament" limits a step to that
     * tab. A step holding the hidden secret cannot be copied until the secret is revealed.
     */
    $sections = [
        'login' => [
            'title' => 'Login with SmartID',
            'badge' => null,
            'intro' => null,
            'steps' => [
                ['.env', $env('/'), 'Tambahkan di akhir file.', 'laravel', true],
                ['.env', $env('/admin'), 'Tambahkan di akhir file. Sesuaikan SMARTID_HOME dengan path panel Filament.', 'filament', true],
                ['config/services.php', $services, 'Tambahkan di dalam array return.', null, false],
                [$migrationFile, $migration, 'File baru.', null, false],
                ['app/Models/Concerns/HasSmartId.php', $trait, 'File baru.', null, false],
                ['app/Models/User.php', 'use Concerns\HasSmartId;', 'Tambahkan di dalam class User, di bawah baris use yang sudah ada.', null, false],
                ['app/Http/Controllers/Auth/SmartIdController.php', $controller, 'File baru.', null, false],
                ['routes/web.php', $routes, 'Tambahkan di akhir file.', null, false],
                ['resources/views/auth/login.blade.php (view login Anda)', '<a href="{{ route(\'smartid.redirect\') }}" class="btn">Login with SmartID</a>', 'Tambahkan di halaman login.', 'laravel', false],
                ['app/Providers/Filament/AdminPanelProvider.php', $filamentLoginHook, 'Tambahkan di dalam ->panel($panel).', 'filament', false],
                ['resources/views/filament/smartid-login.blade.php', $filamentButton, 'File baru.', 'filament', false],
                ['terminal', "php artisan migrate\nphp artisan optimize:clear", 'Jalankan, lalu klik "Login with SmartID" di halaman login.', null, false],
            ],
        ],
        'sync' => [
            'title' => 'Sync otomatis',
            'badge' => 'Opsional · disarankan',
            'intro' => 'Tanpa ini, akses hanya dicek saat login. Dengan ini, user yang resign / dipindah / tidak lagi diizinkan ter-logout paling lambat 15 menit.',
            'steps' => [
                ['app/Http/Middleware/EnsureSmartIdAccess.php', $middleware, 'File baru.', null, false],
                ['bootstrap/app.php', '$middleware->web(append: [\App\Http\Middleware\EnsureSmartIdAccess::class]);', 'Tambahkan di dalam ->withMiddleware(function (Middleware $middleware) { ... }).', 'laravel', false],
                ['app/Providers/Filament/AdminPanelProvider.php', $filamentAuthMiddleware, 'Di dalam ->panel($panel): ganti ->authMiddleware([...]) yang sudah ada dengan blok ini.', 'filament', false],
                ['app/Console/Commands/SmartIdSync.php', $command, 'File baru.', null, false],
                ['routes/console.php', "\\Illuminate\\Support\\Facades\\Schedule::command('smartid:sync')->everyFifteenMinutes();", 'Tambahkan di akhir file. Pastikan cron schedule:run aktif di server.', null, false],
                ['terminal', 'php artisan smartid:sync', 'Jalankan. Hasil: "N user tersinkron, 0 dikunci".', null, false],
            ],
        ],
    ];
@endphp

<div x-data="{ stack: 'laravel' }" class="space-y-4">
    <div role="tablist" class="tabs tabs-boxed w-fit">
        <button type="button" role="tab" class="tab" :class="stack === 'laravel' && 'tab-active'" x-on:click="stack = 'laravel'">Laravel</button>
        <button type="button" role="tab" class="tab" :class="stack === 'filament' && 'tab-active'" x-on:click="stack = 'filament'">Laravel + Filament</button>
    </div>

    @foreach (['laravel', 'filament'] as $stack)
        <div class="space-y-6" x-show="stack === '{{ $stack }}'" @if ($stack !== 'laravel') x-cloak @endif>
            @foreach ($sections as $key => $section)
                <section class="space-y-3" data-section="{{ $key }}">
                    <div>
                        <h3 class="flex flex-wrap items-center gap-2 font-semibold">
                            {{ $section['title'] }}
                            @if ($section['badge'])
                                <span class="badge badge-info badge-sm">{{ $section['badge'] }}</span>
                            @endif
                        </h3>
                        @if ($section['intro'])
                            <p class="text-xs text-base-content/60">{{ $section['intro'] }}</p>
                        @endif
                    </div>

                    <ol class="space-y-4">
                        @foreach (collect($section['steps'])->filter(fn (array $step) => $step[3] === null || $step[3] === $stack)->values() as $index => [$file, $code, $note, $only, $holdsSecret])
                            <li class="flex gap-3" x-data="{ copied: false }">
                                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-content">{{ $index + 1 }}</span>
                                <div class="min-w-0 grow space-y-1.5">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <p class="font-mono text-xs font-semibold">{{ $file }}</p>
                                        @if ($holdsSecret && $secretHidden && $revealClientId)
                                            <button type="button" class="btn btn-warning btn-xs" wire:click="revealSecret('{{ $revealClientId }}')">Tampilkan secret untuk menyalin</button>
                                        @else
                                            <button type="button" class="btn btn-ghost btn-xs"
                                                x-on:click="navigator.clipboard.writeText($refs.code.innerText); copied = true; setTimeout(() => copied = false, 1500)">
                                                <span x-show="! copied">Salin</span><span x-show="copied" x-cloak>✓ Tersalin</span>
                                            </button>
                                        @endif
                                    </div>
                                    <pre class="max-h-96 overflow-auto rounded-box bg-[#0d1117] p-3 text-xs text-[#c9d1d9]"><code x-ref="code">{{ $code }}</code></pre>
                                    <p class="text-xs text-base-content/60">{{ $note }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endforeach
        </div>
    @endforeach
</div>
