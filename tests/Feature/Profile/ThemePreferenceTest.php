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

    $this->actingAs($user)->putJson(route('profile.appearance.update'), ['theme' => 'desk'])
        ->assertOk()
        ->assertExactJson(['theme' => 'desk', 'appearance' => 'system']);

    $this->actingAs($user)->putJson(route('profile.appearance.update'), ['appearance' => 'dark'])
        ->assertOk()
        ->assertExactJson(['theme' => 'desk', 'appearance' => 'dark']);

    expect($user->fresh())
        ->theme->toBe(UserTheme::Desk)
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
    $this->putJson(route('profile.appearance.update'), ['theme' => 'desk'])->assertUnauthorized();
});

test('every page starts with the theme saved on the account', function () {
    $user = User::factory()->create(['theme' => 'desk', 'appearance' => 'dark']);

    $this->actingAs($user)->get(route('profile.show'))
        ->assertOk()
        ->assertSee('\u0022theme\u0022:\u0022desk\u0022', false)
        ->assertSee('\u0022appearance\u0022:\u0022dark\u0022', false);
});

test('the profile offers the Default and Nakama Desk themes', function () {
    $this->actingAs(User::factory()->create())->get(route('profile.show'))
        ->assertOk()
        ->assertSee('Tema Default')
        ->assertSee('Tema Nakama Desk')
        ->assertSee('Pilihan tema dan mode tersimpan di akunmu');
});

test('each theme has a light and a dark variant', function () {
    expect(UserTheme::Default->dataTheme(false))->toBe('light')
        ->and(UserTheme::Default->dataTheme(true))->toBe('dark')
        ->and(UserTheme::Desk->dataTheme(false))->toBe('desk')
        ->and(UserTheme::Desk->dataTheme(true))->toBe('desk-dark');
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

test('accounts on the former "candy" theme move to Cinnamoroll', function () {
    $user = User::factory()->create();
    DB::table('users')->where('id', $user->id)->update(['theme' => 'candy']);

    (require database_path('migrations/2026_10_21_134836_rename_candy_theme_to_cinnamoroll.php'))->up();

    expect($user->fresh()->theme)->toBe(UserTheme::Cinnamoroll)
        ->and(UserTheme::Cinnamoroll->dataTheme(false))->toBe('cinnamoroll')
        ->and(UserTheme::Cinnamoroll->style())->toBe('toon');
});
