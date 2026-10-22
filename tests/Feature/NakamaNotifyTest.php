<?php

use App\Livewire\Tech\UiKit;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

function techUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('tech-sentry.view'));

    return $user;
}

test('the app layout renders the Nakama toaster and dialog host instead of Mary\'s toaster', function () {
    $this->actingAs(techUser())
        ->get(route('dev.ui-kit'))
        ->assertOk()
        ->assertSee('x-data="nakamaToaster()"', false)
        ->assertSee('x-data="nakamaDialogHost()"', false)
        ->assertSee('x-on:mary-toast.window', false)
        ->assertDontSee('mary-toaster', false);
});

test('a flashed message is shown once as a toast and then removed from the session', function () {
    $this->actingAs(techUser())
        ->withSession(['success' => 'Data berhasil disimpan', 'error' => 'Gagal kirim'])
        ->get(route('dev.ui-kit'))
        ->assertOk()
        ->assertSee('data-nakama-flash="success"', false)
        ->assertSee('data-nakama-flash="error"', false)
        ->assertSee('Data berhasil disimpan');

    expect(session()->has('success'))->toBeFalse();

    $this->get(route('dev.ui-kit'))->assertDontSee('data-nakama-flash', false);
});

test('the UI kit page is hidden (404) from anyone without the Sentry permission', function () {
    $this->actingAs(User::factory()->create())->get(route('dev.ui-kit'))->assertNotFound();
});

test('$this->alert() and $this->confirmThen() dispatch the popups for the dialog host', function () {
    $this->actingAs(techUser());

    $component = Livewire::test(UiKit::class);

    $component->call('serverAlert', 'warning')
        ->assertDispatched('nakama-alert', type: 'warning', title: 'Perhatian');

    $component->call('askServerConfirm')
        ->assertDispatched('nakama-confirm', fn (string $event, array $params) => $params['componentId'] === $component->instance()->getId()
            && $params['method'] === 'confirmed'
            && $params['params'] === ['server']
            && $params['variant'] === 'question');
});

test('the confirmed callback runs the action and closes the confirm modal', function () {
    $this->actingAs(techUser());

    Livewire::test(UiKit::class)
        ->set('showConfirmModal', true)
        ->call('confirmed', 'confirm-modal')
        ->assertSet('confirmedCount', 1)
        ->assertSet('showConfirmModal', false);
});

test('a flash from a Livewire action is rendered as a toast in the component', function () {
    $this->actingAs(techUser());

    Livewire::test(UiKit::class)
        ->call('flashMessage')
        ->assertSeeHtml('data-nakama-flash="success"');
});

test('the confirm modal renders its texts and wires the confirm action', function () {
    $this->actingAs(techUser());

    Livewire::test(UiKit::class)
        ->assertSeeHtml('Simpan perubahan?')
        ->assertSeeHtml('wire:click="confirmed(&#039;confirm-modal&#039;)"')
        ->assertSeeHtml('wire:target="confirmed"')
        ->assertSeeHtml('nk-dialog--question')
        ->assertSeeHtml('x-on:click="open = false"');
});

test('a confirm modal with its own cancel method calls it', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('profile.security-settings')
        ->assertSeeHtml('$wire.closeDeleteModal()')
        ->assertSeeHtml('wire:click="deletePasskey"');
});

test('the old delete modals use the Nakama confirm modal', function () {
    $views = collect(glob(resource_path('views/{livewire,pages}/**/*.blade.php'), GLOB_BRACE))
        ->merge(glob(resource_path('views/{livewire,pages}/**/**/*.blade.php'), GLOB_BRACE))
        ->filter(fn (string $path) => str_contains(file_get_contents($path), 'showDeleteModal'));

    expect($views)->not->toBeEmpty();

    foreach ($views as $path) {
        $content = file_get_contents($path);

        expect(str_contains($content, '<x-nakama.confirm-modal'))->toBeTrue($path)
            ->and(str_contains($content, '<x-modal wire:model="showDeleteModal"'))->toBeFalse($path);
    }
});

test('no view uses the browser\'s native confirm() or alert() any more', function () {
    $views = collect(glob(resource_path('views/**/*.blade.php')))
        ->merge(glob(resource_path('views/**/**/*.blade.php')))
        ->merge(glob(resource_path('views/**/**/**/*.blade.php')));

    foreach ($views as $path) {
        expect(preg_match('/(?<![.\w>])(confirm|alert)\(\s*[\'"]/', file_get_contents($path)))->toBe(0, $path);
    }
});
