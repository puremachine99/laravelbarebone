<?php

use App\Enums\UserAppearance;
use App\Enums\UserTheme;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('a new user starts on the default theme following the system appearance', function () {
    $user = User::factory()->create()->fresh();

    expect($user->theme)->toBe(UserTheme::Default)
        ->and($user->appearance)->toBe(UserAppearance::System);
});

test('the theme and appearance are saved on the account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->putJson(route('profile.appearance.update'), ['theme' => 'espresso'])
        ->assertOk()
        ->assertExactJson(['theme' => 'espresso', 'appearance' => 'system']);

    $this->actingAs($user)->putJson(route('profile.appearance.update'), ['appearance' => 'dark'])
        ->assertOk()
        ->assertExactJson(['theme' => 'espresso', 'appearance' => 'dark']);

    expect($user->fresh())
        ->theme->toBe(UserTheme::Espresso)
        ->appearance->toBe(UserAppearance::Dark);
});

test('unknown themes and appearances are rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->putJson(route('profile.appearance.update'), ['theme' => 'neon', 'appearance' => 'dim'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['theme', 'appearance']);

    expect($user->fresh()->theme)->toBe(UserTheme::Default);
});

test('guests cannot save a theme', function () {
    $this->putJson(route('profile.appearance.update'), ['theme' => 'espresso'])->assertUnauthorized();
});

test('every page starts with the theme saved on the account', function () {
    $user = User::factory()->create(['theme' => 'espresso', 'appearance' => 'dark']);

    $this->actingAs($user)->get(route('profile.show'))
        ->assertOk()
        ->assertSee('\u0022theme\u0022:\u0022espresso\u0022', false)
        ->assertSee('\u0022appearance\u0022:\u0022dark\u0022', false);
});

test('the profile offers the Default and Espresso themes', function () {
    $this->actingAs(User::factory()->create())->get(route('profile.show'))
        ->assertOk()
        ->assertSee('Tema Default')
        ->assertSee('Tema Espresso')
        ->assertSee('Pilihan tema dan mode tersimpan di akunmu');
});

test('each theme has a light and a dark variant', function () {
    expect(UserTheme::Default->dataTheme(false))->toBe('light')
        ->and(UserTheme::Default->dataTheme(true))->toBe('dark')
        ->and(UserTheme::Espresso->dataTheme(false))->toBe('espresso')
        ->and(UserTheme::Espresso->dataTheme(true))->toBe('espresso-dark');
});

test('every registered theme gets a card in the profile picker', function () {
    $response = $this->actingAs(User::factory()->create())->get(route('profile.show'))->assertOk();

    foreach (UserTheme::cases() as $theme) {
        $response->assertSee('Tema '.$theme->label())->assertSee($theme->description());
    }
});

test('a theme other than the default uses its own name for the daisyUI themes', function () {
    foreach (UserTheme::cases() as $theme) {
        if ($theme === UserTheme::Default) {
            continue;
        }

        expect($theme->dataTheme(false))->toBe($theme->value)
            ->and($theme->dataTheme(true))->toBe($theme->value.'-dark');
    }
});

test('Organic Warm can be chosen and is a plain theme, not a toon one', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->putJson(route('profile.appearance.update'), ['theme' => 'organic'])
        ->assertOk()
        ->assertExactJson(['theme' => 'organic', 'appearance' => 'system']);

    expect($user->fresh()->theme)->toBe(UserTheme::Organic)
        ->and(UserTheme::Organic->dataTheme(false))->toBe('organic')
        ->and(UserTheme::Organic->dataTheme(true))->toBe('organic-dark')
        ->and(UserTheme::Organic->style())->toBe('default')
        ->and(UserTheme::toonValues())->not->toContain('organic');

    $this->actingAs($user)->get(route('profile.show'))
        ->assertOk()
        ->assertSee('Tema Organic Warm')
        ->assertSee('\u0022theme\u0022:\u0022organic\u0022', false);
});

test('Neo Brutalism is its own toon theme and leaves Cinnamoroll alone', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->putJson(route('profile.appearance.update'), ['theme' => 'neobrutalism'])
        ->assertOk()
        ->assertExactJson(['theme' => 'neobrutalism', 'appearance' => 'system']);

    expect($user->fresh()->theme)->toBe(UserTheme::Neobrutalism)
        ->and(UserTheme::Neobrutalism->dataTheme(true))->toBe('neobrutalism-dark')
        ->and(UserTheme::toonValues())->toContain('neobrutalism', 'cinnamoroll')
        ->and(file_get_contents(resource_path('css/themes/neobrutalism.css')))->not->toContain('cinnamoroll')
        ->and(file_get_contents(resource_path('css/themes/neobrutalism.css')))->toContain('--radius-box: 0;', 'border-radius: 0;', '[data-theme^="neobrutalism"] .app-topbar,')
        ->and(UserTheme::Neobrutalism->preview()['radius'])->toBe('0');
});

test('accounts on the former "candy" theme move to Cinnamoroll', function () {
    $user = User::factory()->create();
    DB::table('users')->where('id', $user->id)->update(['theme' => 'candy']);

    (require database_path('migrations/2026_10_21_134836_rename_candy_theme_to_cinnamoroll.php'))->up();

    expect($user->fresh()->theme)->toBe(UserTheme::Cinnamoroll)
        ->and(UserTheme::Cinnamoroll->dataTheme(false))->toBe('cinnamoroll')
        ->and(UserTheme::Cinnamoroll->style())->toBe('toon');
});

test('accounts on the former "glass" theme move to Organic Warm', function () {
    $user = User::factory()->create();
    DB::table('users')->where('id', $user->id)->update(['theme' => 'glass']);

    (require database_path('migrations/2026_10_23_003515_rename_glass_theme_to_organic.php'))->up();

    expect($user->fresh()->theme)->toBe(UserTheme::Organic)
        ->and(UserTheme::tryFrom('glass'))->toBeNull();
});

test('Mono Brutal is a square, monochrome toon theme with crimson accents', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->putJson(route('profile.appearance.update'), ['theme' => 'mono'])
        ->assertOk()
        ->assertExactJson(['theme' => 'mono', 'appearance' => 'system']);

    $css = file_get_contents(resource_path('css/themes/mono.css'));

    expect($user->fresh()->theme)->toBe(UserTheme::Mono)
        ->and(UserTheme::Mono->dataTheme(true))->toBe('mono-dark')
        ->and(UserTheme::toonValues())->toContain('mono')
        ->and(UserTheme::Mono->preview()['accent'])->toBe('#DC143C')
        ->and($css)->toContain('--chart-1: #DC143C;', '--radius-box: 0;', 'border-radius: 0;', '[data-theme^="mono"] .app-topbar,')
        ->and($css)->toContain('--tile-1: #FFFFFF;', '--tile-2: #F2F2F2;');

    $this->actingAs($user)->get(route('profile.show'))->assertOk()->assertSee('Tema Mono Brutal');
});

test('accounts on the retired material, cyber or ledger themes move to Mono Brutal', function () {
    $users = collect(['material', 'cyber', 'ledger'])->map(function (string $theme) {
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['theme' => $theme]);

        return $user;
    });

    (require database_path('migrations/2026_10_23_005019_move_retired_themes_to_mono.php'))->up();

    foreach ($users as $user) {
        expect($user->fresh()->theme)->toBe(UserTheme::Mono);
    }

    expect(UserTheme::tryFrom('ledger'))->toBeNull()
        ->and(file_get_contents(base_path('vite.config.js')))->not->toContain('Newsreader');
});
