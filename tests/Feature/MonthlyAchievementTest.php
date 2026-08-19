<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\FoContact;
use App\Models\FoFunnel;
use App\Models\FoFunnelActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

function makeMonthlyFoEmployee(string $roleName): Employee
{
    $department = Department::firstOrCreate(['name' => 'Departemen Finance']);
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $user->assignRole(Role::findOrCreate($roleName));

    return Employee::create(['user_id' => $user->id, 'department_id' => $department->id]);
}

test('guests are redirected to login', function () {
    $this->get(route('investors.monthly'))
        ->assertRedirect(route('login'));
});

test('unauthorized users without access-investors permission cannot access the page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('investors.monthly'))
        ->assertStatus(403);
});

test('page is rendered for authorized users', function () {
    $admin = makeMonthlyFoEmployee('Investor Admin');
    $this->actingAs($admin->user);

    Volt::test('investors.monthly-index')
        ->assertOk()
        ->assertSee('Monthly Achievement')
        ->assertSee('Team Total');
});

test('a plain funding officer is locked to their own data', function () {
    $fo = makeMonthlyFoEmployee('Funding Officer');
    $this->actingAs($fo->user);

    $component = Volt::test('investors.monthly-index');

    $component->assertOk()->assertSee($fo->user->name);

    expect($component->get('viewAsEmployeeId'))->toBe($fo->id);
});

test('monthly realisasi counts leads, ql and closing only within the selected month', function () {
    $admin = makeMonthlyFoEmployee('Investor Admin');
    $fo = makeMonthlyFoEmployee('Funding Officer');
    $contact = FoContact::factory()->create(['employee_id' => $fo->id]);

    FoFunnel::factory()->create([
        'fo_contact_id' => $contact->id,
        'employee_id' => $fo->id,
        'tgl_leads' => now()->startOfMonth()->addDays(2)->toDateString(),
        'tgl_qualified' => now()->startOfMonth()->addDays(3)->toDateString(),
    ]);

    FoFunnel::factory()->create([
        'fo_contact_id' => $contact->id,
        'employee_id' => $fo->id,
        'stage' => 'closing',
        'tgl_leads' => now()->startOfMonth()->addDays(4)->toDateString(),
        'tgl_closing' => now()->startOfMonth()->addDays(10)->toDateString(),
        'nominal_investasi' => 250_000_000,
    ]);

    // Outside the selected month — must not be counted.
    FoFunnel::factory()->create([
        'fo_contact_id' => $contact->id,
        'employee_id' => $fo->id,
        'tgl_leads' => now()->subMonths(2)->toDateString(),
    ]);

    $this->actingAs($admin->user);

    $rows = collect(
        Volt::test('investors.monthly-index')->instance()->realisasiPerFo()
    )->keyBy('name');

    $row = $rows[$fo->user->name];

    expect($row['leads'])->toBe(2)
        ->and($row['ql'])->toBe(1)
        ->and($row['closing'])->toBe(1)
        ->and((float) $row['nilai_closing'])->toBe(250_000_000.0)
        ->and($row['konversi'])->toBe(50.0);
});

test('konversi is null when there are no leads in the selected month', function () {
    $admin = makeMonthlyFoEmployee('Investor Admin');
    makeMonthlyFoEmployee('Funding Officer');
    $this->actingAs($admin->user);

    $rows = collect(
        Volt::test('investors.monthly-index')->instance()->realisasiPerFo()
    );

    expect($rows->first()['konversi'])->toBeNull();
});

test('total tim row sums every fo currently shown', function () {
    $admin = makeMonthlyFoEmployee('Investor Admin');
    $foA = makeMonthlyFoEmployee('Funding Officer');
    $foB = makeMonthlyFoEmployee('Funding Officer');

    $contactA = FoContact::factory()->create(['employee_id' => $foA->id]);
    $contactB = FoContact::factory()->create(['employee_id' => $foB->id]);

    FoFunnel::factory()->create(['fo_contact_id' => $contactA->id, 'employee_id' => $foA->id, 'tgl_leads' => now()->startOfMonth()->toDateString()]);
    FoFunnel::factory()->create(['fo_contact_id' => $contactB->id, 'employee_id' => $foB->id, 'tgl_leads' => now()->startOfMonth()->toDateString()]);
    FoFunnel::factory()->create(['fo_contact_id' => $contactB->id, 'employee_id' => $foB->id, 'tgl_leads' => now()->startOfMonth()->toDateString()]);

    $this->actingAs($admin->user);

    $total = Volt::test('investors.monthly-index')->instance()->totalTim();

    expect($total['leads'])->toBe(3);
});

test('masuk sebagai selector scopes the monthly table down to a single fo', function () {
    $admin = makeMonthlyFoEmployee('Investor Admin');
    $foA = makeMonthlyFoEmployee('Funding Officer');
    $foB = makeMonthlyFoEmployee('Funding Officer');

    $contactA = FoContact::factory()->create(['employee_id' => $foA->id]);
    $contactB = FoContact::factory()->create(['employee_id' => $foB->id]);

    FoFunnel::factory()->create(['fo_contact_id' => $contactA->id, 'employee_id' => $foA->id, 'tgl_leads' => now()->startOfMonth()->toDateString()]);
    FoFunnel::factory()->create(['fo_contact_id' => $contactB->id, 'employee_id' => $foB->id, 'tgl_leads' => now()->startOfMonth()->toDateString()]);

    $this->actingAs($admin->user);

    $rows = collect(
        Volt::test('investors.monthly-index')
            ->set('viewAsEmployeeId', $foB->id)
            ->instance()
            ->realisasiPerFo()
    );

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['name'])->toBe($foB->user->name);
});

test('switching the selected month changes the realisasi figures', function () {
    $admin = makeMonthlyFoEmployee('Investor Admin');
    $fo = makeMonthlyFoEmployee('Funding Officer');
    $contact = FoContact::factory()->create(['employee_id' => $fo->id]);

    FoFunnel::factory()->create([
        'fo_contact_id' => $contact->id,
        'employee_id' => $fo->id,
        'tgl_leads' => now()->subMonth()->startOfMonth()->addDays(3)->toDateString(),
    ]);

    $this->actingAs($admin->user);

    $component = Volt::test('investors.monthly-index');

    $currentMonthRow = collect($component->instance()->realisasiPerFo())->firstWhere('name', $fo->user->name);
    expect($currentMonthRow['leads'])->toBe(0);

    $component->set('selectedMonth', (int) now()->subMonth()->month)
        ->set('selectedYear', (int) now()->subMonth()->year);

    $lastMonthRow = collect($component->instance()->realisasiPerFo())->firstWhere('name', $fo->user->name);
    expect($lastMonthRow['leads'])->toBe(1);
});

test('pitching count drops once the funnel is moved back to a stage before pitching', function () {
    $admin = makeMonthlyFoEmployee('Investor Admin');
    $fo = makeMonthlyFoEmployee('Funding Officer');
    $contact = FoContact::factory()->create(['employee_id' => $fo->id]);

    $funnel = FoFunnel::factory()->create([
        'fo_contact_id' => $contact->id,
        'employee_id' => $fo->id,
        'stage' => 'pitching',
    ]);

    FoFunnelActivity::factory()->create([
        'fo_funnel_id' => $funnel->id,
        'stage' => 'pitching',
        'tanggal' => now()->startOfMonth()->addDays(1)->toDateString(),
    ]);

    $this->actingAs($admin->user);

    $rowWhilePitching = collect(
        Volt::test('investors.monthly-index')->instance()->realisasiPerFo()
    )->firstWhere('name', $fo->user->name);

    expect($rowWhilePitching['pitching'])->toBe(1);

    $funnel->update(['stage' => 'ql']);

    $rowAfterMovingBack = collect(
        Volt::test('investors.monthly-index')->instance()->realisasiPerFo()
    )->firstWhere('name', $fo->user->name);

    expect($rowAfterMovingBack['pitching'])->toBe(0);
});

test('pitching and follow up count distinct funnels once within the month even with multiple activity logs', function () {
    $admin = makeMonthlyFoEmployee('Investor Admin');
    $fo = makeMonthlyFoEmployee('Funding Officer');
    $contact = FoContact::factory()->create(['employee_id' => $fo->id]);

    $funnel = FoFunnel::factory()->create([
        'fo_contact_id' => $contact->id,
        'employee_id' => $fo->id,
        'stage' => 'pitching',
    ]);

    FoFunnelActivity::factory()->create([
        'fo_funnel_id' => $funnel->id,
        'stage' => 'pitching',
        'tanggal' => now()->startOfMonth()->addDays(1)->toDateString(),
    ]);
    FoFunnelActivity::factory()->create([
        'fo_funnel_id' => $funnel->id,
        'stage' => 'pitching',
        'tanggal' => now()->startOfMonth()->addDays(5)->toDateString(),
    ]);

    $this->actingAs($admin->user);

    $row = collect(
        Volt::test('investors.monthly-index')->instance()->realisasiPerFo()
    )->firstWhere('name', $fo->user->name);

    expect($row['pitching'])->toBe(1);
});
