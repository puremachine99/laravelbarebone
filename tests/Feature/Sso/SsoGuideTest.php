<?php

use App\Livewire\Tech\SsoClients;
use App\Models\OAuthClient;
use App\Models\User;
use App\Services\Sso\SsoClientManager;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * @param  array<string, mixed>  $overrides
 */
function guideClient(array $overrides = []): OAuthClient
{
    return app(SsoClientManager::class)->create([
        'name' => 'PMO App',
        'description' => null,
        'redirect_uris' => ['https://pmo.test/auth/smartid/callback'],
        'confidential' => true,
        'scopes' => ['profile', 'email', 'department'],
        'skip_authorization' => false,
        'allowed_roles' => [],
        'allowed_department_ids' => [],
        ...$overrides,
    ]);
}

function guideAdmin(): User
{
    return User::factory()->create()->assignRole(Role::findOrCreate('Super Admin'));
}

/**
 * Every copy-paste block of the rendered guide, as [file, code].
 *
 * @return array<int, array{0: string, 1: string}>
 */
function guideBlocks(string $html): array
{
    preg_match_all('#<p class="font-mono text-xs font-semibold">(.*?)</p>.*?<code x-ref="code">(.*?)</code>#s', $html, $matches, PREG_SET_ORDER);

    return array_map(fn (array $match) => [
        html_entity_decode(trim($match[1]), ENT_QUOTES),
        html_entity_decode($match[2], ENT_QUOTES),
    ], $matches);
}

function renderedGuide(?string $secret = 'plain-secret-value', string $redirect = 'https://pmo.test/auth/smartid/callback'): string
{
    return view('partials.sso-laravel-guide', [
        'host' => 'https://smartid.test',
        'clientId' => 'client-uuid',
        'clientSecret' => $secret,
        'revealClientId' => 'client-uuid',
        'redirect' => $redirect,
        'scopes' => 'profile email department',
    ])->render();
}

test('the guide masks the secret until it is revealed, and cannot be copied while masked', function () {
    $client = guideClient();
    $this->actingAs(guideAdmin())->withSession(['auth.password_confirmed_at' => time()]);

    Livewire::test(SsoClients::class)
        ->call('openGuide', $client->getKey())
        ->assertSee('SMARTID_CLIENT_SECRET='.str_repeat('•', 40))
        ->assertSee('Tampilkan secret untuk menyalin')
        ->assertSee("SMARTID_CLIENT_ID={$client->getKey()}")
        ->assertDontSee($client->storedSecret->secret)
        ->call('revealSecret', $client->getKey())
        ->assertSee("SMARTID_CLIENT_SECRET={$client->storedSecret->secret}")
        ->assertDontSee('Tampilkan secret untuk menyalin');
});

test('the guide is filled with the app values', function () {
    $code = collect(guideBlocks(renderedGuide(redirect: 'https://pmo.test/sso/back')))->pluck(1)->implode("\n");

    expect($code)
        ->toContain('SMARTID_HOST=https://smartid.test')
        ->toContain('SMARTID_REDIRECT_URI=https://pmo.test/sso/back')
        ->toContain('SMARTID_SCOPES="profile email department"')
        ->toContain("Route::get('/sso/back'")
        ->not->toContain('SESSION_COOKIE');

    expect(renderedGuide(redirect: 'http://localhost:8001/auth/smartid/callback'))->toContain('SESSION_COOKIE=client_app_session');
});

test('every PHP block in the guide is valid PHP once pasted in its file', function () {
    $blocks = collect(guideBlocks(renderedGuide()))->unique(fn (array $block) => $block[0].$block[1]);

    // Snippets that are pasted inside an existing file get the context they land in.
    $wrap = fn (string $file, string $code) => match (true) {
        str_starts_with($code, '<?php') => $code,
        $file === 'config/services.php' => "<?php return [\n{$code}\n];",
        $file === 'app/Models/User.php' => "<?php namespace App\\Models; class User { {$code} }",
        $file === 'bootstrap/app.php' => "<?php function (\$middleware) { {$code} };",
        str_starts_with($file, 'app/Providers/Filament') => "<?php \$panel{$code};",
        default => "<?php {$code}",
    };

    $phpBlocks = $blocks->filter(fn (array $block) => str_ends_with($block[0], '.php') && ! str_ends_with($block[0], '.blade.php'));

    expect($phpBlocks)->toHaveCount(12);

    $phpBlocks->each(function (array $block) use ($wrap) {
        [$file, $code] = $block;

        try {
            token_get_all($wrap($file, $code), TOKEN_PARSE);
        } catch (ParseError $error) {
            throw new RuntimeException("{$file}: {$error->getMessage()} (line {$error->getLine()})");
        }
    });
});

test('both stacks get every shared step', function () {
    $files = collect(guideBlocks(renderedGuide()))->map(fn (array $block) => $block[0]);

    foreach (['config/services.php', 'app/Models/Concerns/HasSmartId.php', 'app/Http/Controllers/Auth/SmartIdController.php',
        'app/Http/Middleware/EnsureSmartIdAccess.php', 'app/Console/Commands/SmartIdSync.php', 'routes/web.php', 'routes/console.php'] as $file) {
        expect($files->filter(fn (string $name) => $name === $file))->toHaveCount(2, $file);
    }

    expect($files->filter(fn (string $name) => $name === 'terminal'))->toHaveCount(4)
        ->and($files->filter(fn (string $name) => $name === 'bootstrap/app.php'))->toHaveCount(1)
        ->and($files->filter(fn (string $name) => $name === 'app/Providers/Filament/AdminPanelProvider.php'))->toHaveCount(2);
});

test('login works on its own: the optional sync section holds everything sync needs', function () {
    $html = renderedGuide();

    preg_match_all('#<section class="space-y-3" data-section="(\w+)">(.*?)</section>#s', $html, $sections, PREG_SET_ORDER);

    $login = collect($sections)->where(1, 'login')->pluck(2)->implode('');
    $sync = collect($sections)->where(1, 'sync')->pluck(2)->implode('');

    expect($sync)->toContain('Opsional · disarankan')
        ->toContain('class EnsureSmartIdAccess')
        ->toContain('class SmartIdSync')
        ->toContain('php artisan smartid:sync');

    expect($login)->toContain('class SmartIdController')
        ->not->toContain('EnsureSmartIdAccess')
        ->not->toContain('smartid:sync');
});
