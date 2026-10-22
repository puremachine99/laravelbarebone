<?php

use App\Livewire\Tech\ApiConsumers;
use App\Models\ApiConsumer;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

function apiKeysAdmin(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-technology'), Permission::findOrCreate('tech-api-keys.view'));

    return $user;
}

test('the API keys page needs its permission', function () {
    $this->actingAs(User::factory()->create())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('tech.api-keys'))
        ->assertForbidden();

    $this->actingAs(apiKeysAdmin())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('tech.api-keys'))
        ->assertOk()
        ->assertSee('API Keys');
});

test('issuing a key stores only its hash, normalizes the websites and shows the token once', function () {
    $this->actingAs(apiKeysAdmin());

    $component = Livewire::test(ApiConsumers::class)
        ->call('create')
        ->set('name', 'Portal Mitra')
        ->set('allowedOrigins', "https://Portal.Contoh.go.id/form\n*.mitra.id, localhost:3000")
        ->set('rateLimitPerMinute', 60)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showToken', true);

    $token = $component->get('issuedToken');
    $consumer = ApiConsumer::sole();

    expect($token)->toStartWith(ApiConsumer::TOKEN_PREFIX)
        ->and($consumer->token_hash)->toBe(ApiConsumer::hashToken($token))
        ->and($consumer->token_prefix)->toBe(substr($token, 0, 12))
        ->and($consumer->allowed_origins)->toBe(['portal.contoh.go.id', '*.mitra.id', 'localhost:3000'])
        ->and($consumer->rate_limit_per_minute)->toBe(60);

    $component->call('hideToken')->assertSet('issuedToken', null)->assertSet('showToken', false);
});

test('a key needs a website unless server requests are allowed', function () {
    $this->actingAs(apiKeysAdmin());

    Livewire::test(ApiConsumers::class)
        ->set('name', 'Tanpa Website')
        ->call('save')
        ->assertHasErrors('allowedOrigins');

    Livewire::test(ApiConsumers::class)
        ->set('name', 'Backend Saja')
        ->set('allowServerRequests', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(ApiConsumer::where('name', 'Backend Saja')->sole()->allowed_origins)->toBe([]);
});

test('regenerating, revoking and deleting a key', function () {
    $this->actingAs(apiKeysAdmin());
    $consumer = ApiConsumer::factory()->create();
    $oldHash = $consumer->token_hash;

    $component = Livewire::test(ApiConsumers::class)->call('regenerate', $consumer->id);

    expect($consumer->fresh()->token_hash)->not->toBe($oldHash)
        ->and($consumer->fresh()->token_hash)->toBe(ApiConsumer::hashToken($component->get('issuedToken')));

    $component->call('toggleRevoked', $consumer->id);
    expect($consumer->fresh()->isRevoked())->toBeTrue();

    $component->call('toggleRevoked', $consumer->id);
    expect($consumer->fresh()->isRevoked())->toBeFalse();

    $component->call('delete', $consumer->id);
    expect(ApiConsumer::count())->toBe(0);
});

test('actions are refused without the permission', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(ApiConsumers::class)->assertForbidden();
});

test('each consumer row shows its actions inline', function () {
    $this->actingAs(apiKeysAdmin());
    $consumer = ApiConsumer::factory()->create(['name' => 'Portal Mitra']);

    Livewire::test(ApiConsumers::class)
        ->assertSeeHtml('aria-label="Edit Portal Mitra"')
        ->assertSeeHtml('aria-label="Generate token baru Portal Mitra"')
        ->assertSeeHtml('aria-label="Cabut Portal Mitra"')
        ->assertSeeHtml('aria-label="Hapus Portal Mitra"')
        ->assertSeeHtml('wire:click="delete('.$consumer->id.')"');
});
