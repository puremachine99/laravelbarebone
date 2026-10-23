<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * @param  array{title?: string, message?: string, url?: string}  $data
 */
function notifyInApp(User $user, array $data): void
{
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\ExampleNotification',
        'data' => $data,
    ]);
}

test('notifications index page only shows the authenticated user notifications and marks them read', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    notifyInApp($user, ['title' => 'Untuk saya', 'message' => 'Pesan pribadi']);
    notifyInApp($otherUser, ['title' => 'Untuk orang lain', 'message' => 'Bukan untuk saya']);

    $response = Livewire::actingAs($user)->test('notifications.index');
    $response->assertSee('Untuk saya')->assertDontSee('Untuk orang lain');

    $response->call('open', $user->notifications()->first()->id);

    expect($user->notifications()->first()->read_at)->not->toBeNull()
        ->and($otherUser->notifications()->first()->read_at)->toBeNull();
});

test('the notification bell shows the unread count and can mark everything as read', function () {
    $user = User::factory()->create();

    notifyInApp($user, ['title' => 'Satu']);
    notifyInApp($user, ['title' => 'Dua']);

    Livewire::actingAs($user)->test('notification-bell')
        ->assertSee('Satu')
        ->assertSee('Dua')
        ->call('markAllAsRead');

    expect($user->unreadNotifications()->count())->toBe(0);
});

test('every broadcasting notification opts out of the queue', function () {
    // BroadcastNotificationCreated bawaan Laravel implements ShouldBroadcast, jadi
    // tanpa onConnection('sync') siarannya mengendap di tabel jobs sampai ada queue
    // worker. Tidak ada worker di server, jadi ini wajib untuk setiap notifikasi baru.
    $offenders = collect(glob(app_path('Notifications/*.php')))
        ->filter(fn (string $path) => str_contains(file_get_contents($path), "'broadcast'"))
        ->reject(fn (string $path) => str_contains(file_get_contents($path), "onConnection('sync')"))
        ->map(fn (string $path) => basename($path))
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});
