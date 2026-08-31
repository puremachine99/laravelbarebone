<?php

use App\Models\Card;
use App\Models\Contact;
use App\Models\Employee;
use App\Models\FoFunnel;
use App\Models\PipelineProfile;
use App\Models\Region;
use App\Models\User;
use App\Notifications\DealClosedNotification;
use App\Notifications\FundingDealClosedNotification;
use App\Notifications\ManagerVerifiedNotification;
use Database\Seeders\PipelineSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RoleAndPermissionSeeder)->run();
    (new PipelineSeeder)->run();
});

test('closing a deal notifies managers', function () {
    $foUser = User::factory()->create();
    $foUser->givePermissionTo('access-partnership');
    $employee = Employee::create(['user_id' => $foUser->id, 'name' => $foUser->name]);

    $manager = User::factory()->create();
    $manager->givePermissionTo(['access-partnership', 'verify-manager']);

    // A verify-manager holder with no Partnership access at all (e.g. an
    // Investors-side manager) must NOT be notified about Partnership deals.
    $outOfScopeManager = User::factory()->create();
    $outOfScopeManager->givePermissionTo('verify-manager');

    $region = Region::create(['name' => 'Region Jawa', 'code' => 'REG-JAWA']);
    $contact = Contact::create([
        'name' => 'PIC Notif',
        'sumber' => 'SDS Umum',
        'region_id' => $region->id,
        'category' => 'Pemerintah Daerah',
    ]);

    $rencanaStage = PipelineProfile::where('pipeline_name', 'Pitching')->first()
        ->stages()->where('stage_name', 'Rencana')->first();

    $card = Card::create([
        'title' => 'Deal Notif Test',
        'stage_id' => $rencanaStage->id,
        'contact_id' => $contact->id,
        'employee_id' => $employee->id,
        'nominal_value' => 15000000,
        'status' => 'Active',
        'manager_verified' => true,
    ]);

    Livewire::actingAs($foUser)
        ->test('partnership.pitching-index')
        ->call('moveCard', $card->id, 'Terlaksana')
        ->set('decisionType', 'won')
        ->set('projectionMonth', date('Y-m'))
        ->set('projectionWeek', 'Pekan 1')
        ->call('saveTerlaksanaDecision')
        ->call('confirmWonAction');

    $card->refresh();
    expect($card->status)->toBe('won');

    expect($manager->notifications()->count())->toBe(1);
    expect($manager->notifications()->first()->type)->toBe(DealClosedNotification::class);
    expect($manager->notifications()->first()->data['type'])->toBe('deal_closed');

    // The FO themselves should not be notified of their own closing.
    expect($foUser->notifications()->count())->toBe(0);

    // Nor should a manager with no Partnership access.
    expect($outOfScopeManager->notifications()->count())->toBe(0);
});

test('manager verifying a card notifies the owning FO', function () {
    $foUser = User::factory()->create();
    $employee = Employee::create(['user_id' => $foUser->id, 'name' => $foUser->name]);

    $manager = User::factory()->create();
    $manager->givePermissionTo(['access-partnership', 'verify-manager']);

    $region = Region::create(['name' => 'Region Jawa', 'code' => 'REG-JAWA']);
    $contact = Contact::create([
        'name' => 'PIC Notif',
        'sumber' => 'SDS Umum',
        'region_id' => $region->id,
        'category' => 'Pemerintah Daerah',
    ]);

    $terlaksanaStage = PipelineProfile::where('pipeline_name', 'Pitching')->first()
        ->stages()->where('stage_name', 'Terlaksana')->first();

    $card = Card::create([
        'title' => 'Verify Notif Test',
        'stage_id' => $terlaksanaStage->id,
        'contact_id' => $contact->id,
        'employee_id' => $employee->id,
        'nominal_value' => 15000000,
        'status' => 'won',
        'manager_verified' => false,
    ]);

    Livewire::actingAs($manager)
        ->test('partnership.realisasi-index')
        ->call('toggleVerification', $card->id);

    $card->refresh();
    expect($card->manager_verified)->toBeTrue();

    expect($foUser->notifications()->count())->toBe(1);
    expect($foUser->notifications()->first()->type)->toBe(ManagerVerifiedNotification::class);
});

test('notifications index page only shows the authenticated user notifications and marks them read', function () {
    $foUser = User::factory()->create();
    $otherUser = User::factory()->create();

    $region = Region::create(['name' => 'Region Jawa', 'code' => 'REG-JAWA']);
    $contact = Contact::create([
        'name' => 'PIC Isolated',
        'sumber' => 'SDS Umum',
        'region_id' => $region->id,
        'category' => 'Pemerintah Daerah',
    ]);

    $rencanaStage = PipelineProfile::where('pipeline_name', 'Pitching')->first()
        ->stages()->where('stage_name', 'Rencana')->first();

    $card = Card::create([
        'title' => 'Isolated Notif Card',
        'stage_id' => $rencanaStage->id,
        'contact_id' => $contact->id,
        'status' => 'won',
    ]);

    $foUser->notify(new DealClosedNotification($card));
    $otherUser->notify(new DealClosedNotification($card));

    $response = Livewire::actingAs($foUser)->test('notifications.index');
    $response->assertSee('Deal berhasil closing');

    $notificationId = $foUser->notifications()->first()->id;
    $response->call('open', $notificationId);

    expect($foUser->notifications()->first()->read_at)->not->toBeNull();
    expect($otherUser->notifications()->first()->read_at)->toBeNull();
});

test('broadcast notifications use the sync connection so they do not wait for a queue worker', function () {
    $funnel = FoFunnel::factory()->create();
    $recipient = User::factory()->create();

    $message = (new FundingDealClosedNotification($funnel))->toBroadcast($recipient);

    expect($message->connection)->toBe('sync');
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
