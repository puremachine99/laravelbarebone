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

function makeConversionFoEmployee(string $roleName): Employee
{
    $department = Department::firstOrCreate(['name' => 'Departemen Finance']);
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $user->assignRole(Role::findOrCreate($roleName));

    return Employee::create(['user_id' => $user->id, 'department_id' => $department->id]);
}

test('guests are redirected to login', function () {
    $this->get(route('investors.conversion'))
        ->assertRedirect(route('login'));
});

test('unauthorized users without access-investors permission cannot access the page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('investors.conversion'))
        ->assertStatus(403);
});

test('page is rendered for authorized users', function () {
    $admin = makeConversionFoEmployee('Investor Admin');
    $this->actingAs($admin->user);

    Volt::test('investors.conversion-index')
        ->assertOk()
        ->assertSee('Monthly Conversion')
        ->assertSee('Year-to-Date Conversion Summary')
        ->assertSee('Cumulative Funnel');
});

test('a plain funding officer is locked to their own data', function () {
    $fo = makeConversionFoEmployee('Funding Officer');
    $this->actingAs($fo->user);

    $component = Volt::test('investors.conversion-index');

    $component->assertOk()->assertSee($fo->user->name);

    expect($component->get('viewAsEmployeeId'))->toBe($fo->id);
});

test('a month with no leads is marked as having no data', function () {
    $admin = makeConversionFoEmployee('Investor Admin');
    makeConversionFoEmployee('Funding Officer');
    $this->actingAs($admin->user);

    $rows = collect(Volt::test('investors.conversion-index')->instance()->monthlyRows());

    // January (index 0) has no seeded data — as long as we're past January in the test run.
    $january = $rows->first();

    expect($january['hasData'])->toBeFalse()
        ->and($january['lead'])->toBe(0)
        ->and($january['overall'])->toBeNull();
})->skip(fn () => now()->month < 2, 'Needs at least 2 elapsed months in the year to have an empty leading month');

test('monthly conversion percentages are computed stage by stage', function () {
    $admin = makeConversionFoEmployee('Investor Admin');
    $fo = makeConversionFoEmployee('Funding Officer');
    $contact = FoContact::factory()->create(['employee_id' => $fo->id]);

    $monthDate = now()->startOfMonth()->addDays(2)->toDateString();

    $funnels = FoFunnel::factory()->count(10)->create([
        'fo_contact_id' => $contact->id,
        'employee_id' => $fo->id,
        'tgl_leads' => $monthDate,
    ]);

    // 5 of the 10 leads become Qualified Leads.
    $funnels->take(5)->each(fn (FoFunnel $f) => $f->update(['tgl_qualified' => $monthDate]));

    // 4 distinct funnels get a pitching session logged.
    $funnels->take(4)->each(function (FoFunnel $f) use ($monthDate) {
        FoFunnelActivity::factory()->create([
            'fo_funnel_id' => $f->id,
            'stage' => 'pitching',
            'tanggal' => $monthDate,
        ]);
    });

    // Only 1 of those actually closes (won first, then closing).
    $funnels->first()->update([
        'stage' => 'closing',
        'tgl_won' => $monthDate,
        'tgl_closing' => $monthDate,
        'nominal_investasi' => 500_000_000,
    ]);

    $this->actingAs($admin->user);

    $row = collect(Volt::test('investors.conversion-index')->instance()->monthlyRows())->last();

    expect($row['lead'])->toBe(10)
        ->and($row['ql'])->toBe(5)
        ->and($row['pitching'])->toBe(4)
        ->and($row['won'])->toBe(1)
        ->and($row['closing'])->toBe(1)
        ->and((float) $row['nilai_closing'])->toBe(500_000_000.0)
        ->and($row['lead_to_ql'])->toBe(50.0)
        ->and($row['ql_to_pitch'])->toBe(80.0)
        ->and($row['pitch_to_won'])->toBe(25.0)
        ->and($row['won_to_closing'])->toBe(100.0)
        ->and($row['overall'])->toBe(10.0);
});

test('total row sums the raw counts instead of averaging monthly percentages', function () {
    $admin = makeConversionFoEmployee('Investor Admin');
    $fo = makeConversionFoEmployee('Funding Officer');
    $contact = FoContact::factory()->create(['employee_id' => $fo->id]);

    // Current month: 1 lead, 1 closing (100%).
    FoFunnel::factory()->create([
        'fo_contact_id' => $contact->id,
        'employee_id' => $fo->id,
        'stage' => 'closing',
        'tgl_leads' => now()->startOfMonth()->toDateString(),
        'tgl_closing' => now()->startOfMonth()->toDateString(),
        'nominal_investasi' => 100_000_000,
    ]);

    // Last month: 4 leads, 0 closing (0%).
    FoFunnel::factory()->count(4)->create([
        'fo_contact_id' => $contact->id,
        'employee_id' => $fo->id,
        'tgl_leads' => now()->subMonth()->startOfMonth()->toDateString(),
    ]);

    $this->actingAs($admin->user);

    $total = Volt::test('investors.conversion-index')->instance()->totalRow();

    // SUM/SUM: 1 closing out of 5 leads = 20%, not avg(100%, 0%) = 50%.
    expect($total['lead'])->toBe(5)
        ->and($total['closing'])->toBe(1)
        ->and($total['overall'])->toBe(20.0);
})->skip(fn () => now()->month < 2, 'Needs a previous month within the same year');

test('status badges are relative to the year-to-date average, not a fixed threshold', function () {
    $admin = makeConversionFoEmployee('Investor Admin');
    $fo = makeConversionFoEmployee('Funding Officer');
    $contact = FoContact::factory()->create(['employee_id' => $fo->id]);

    // Current month: 1 lead, 1 closing -> 100% overall.
    FoFunnel::factory()->create([
        'fo_contact_id' => $contact->id,
        'employee_id' => $fo->id,
        'stage' => 'closing',
        'tgl_leads' => now()->startOfMonth()->toDateString(),
        'tgl_closing' => now()->startOfMonth()->toDateString(),
    ]);

    // 1 month ago: 5 leads, 1 closing -> 20% overall.
    $contactPrev = FoContact::factory()->create(['employee_id' => $fo->id]);
    FoFunnel::factory()->create([
        'fo_contact_id' => $contactPrev->id,
        'employee_id' => $fo->id,
        'stage' => 'closing',
        'tgl_leads' => now()->subMonth()->startOfMonth()->toDateString(),
        'tgl_closing' => now()->subMonth()->startOfMonth()->toDateString(),
    ]);
    FoFunnel::factory()->count(4)->create([
        'fo_contact_id' => $contactPrev->id,
        'employee_id' => $fo->id,
        'tgl_leads' => now()->subMonth()->startOfMonth()->addDay()->toDateString(),
    ]);

    // 2 months ago: 2 leads, 1 closing -> 50% overall.
    $contactPrev2 = FoContact::factory()->create(['employee_id' => $fo->id]);
    FoFunnel::factory()->create([
        'fo_contact_id' => $contactPrev2->id,
        'employee_id' => $fo->id,
        'stage' => 'closing',
        'tgl_leads' => now()->subMonths(2)->startOfMonth()->toDateString(),
        'tgl_closing' => now()->subMonths(2)->startOfMonth()->toDateString(),
    ]);
    FoFunnel::factory()->create([
        'fo_contact_id' => $contactPrev2->id,
        'employee_id' => $fo->id,
        'tgl_leads' => now()->subMonths(2)->startOfMonth()->addDay()->toDateString(),
    ]);

    $this->actingAs($admin->user);

    $component = Volt::test('investors.conversion-index')->instance();
    $rows = collect($component->monthlyRows())->where('hasData', true)->values();

    // rata-rata = avg(100, 20, 50) = 56.7
    expect(round($component->rataRata(), 1))->toBe(56.7);

    $byOverall = $rows->keyBy('overall');

    expect($component->statusFor($byOverall[100.0]['overall'])['label'])->toBe('≥ average')
        ->and($component->statusFor($byOverall[20.0]['overall'])['label'])->toBe('below average')
        ->and($component->statusFor($byOverall[50.0]['overall'])['label'])->toBe('near average');
})->skip(fn () => now()->month < 3, 'Needs two previous months within the same year');

test('bottleneck identifies the funnel stage with the lowest average conversion', function () {
    $admin = makeConversionFoEmployee('Investor Admin');
    $fo = makeConversionFoEmployee('Funding Officer');
    $contact = FoContact::factory()->create(['employee_id' => $fo->id]);

    $monthDate = now()->startOfMonth()->addDays(2)->toDateString();

    $funnels = FoFunnel::factory()->count(10)->create([
        'fo_contact_id' => $contact->id,
        'employee_id' => $fo->id,
        'tgl_leads' => $monthDate,
    ]);

    $funnels->take(5)->each(fn (FoFunnel $f) => $f->update(['tgl_qualified' => $monthDate]));
    $funnels->take(4)->each(fn (FoFunnel $f) => FoFunnelActivity::factory()->create([
        'fo_funnel_id' => $f->id,
        'stage' => 'pitching',
        'tanggal' => $monthDate,
    ]));
    $funnels->first()->update(['stage' => 'closing', 'tgl_won' => $monthDate, 'tgl_closing' => $monthDate, 'nominal_investasi' => 100_000_000]);

    $this->actingAs($admin->user);

    $ringkasan = Volt::test('investors.conversion-index')->instance()->ringkasan();

    // lead_to_ql=50%, ql_to_pitch=80%, pitch_to_won=25%, won_to_closing=100% — the weakest stage is Pitching -> Won.
    expect($ringkasan['bottleneck']['label'])->toBe('Pitching → Won')
        ->and($ringkasan['total_lead'])->toBe(10)
        ->and($ringkasan['total_closing'])->toBe(1);
});

test('masuk sebagai selector scopes the monthly rows down to a single fo', function () {
    $admin = makeConversionFoEmployee('Investor Admin');
    $foA = makeConversionFoEmployee('Funding Officer');
    $foB = makeConversionFoEmployee('Funding Officer');

    $contactA = FoContact::factory()->create(['employee_id' => $foA->id]);
    $contactB = FoContact::factory()->create(['employee_id' => $foB->id]);

    FoFunnel::factory()->create(['fo_contact_id' => $contactA->id, 'employee_id' => $foA->id, 'tgl_leads' => now()->startOfMonth()->toDateString()]);
    FoFunnel::factory()->count(2)->create(['fo_contact_id' => $contactB->id, 'employee_id' => $foB->id, 'tgl_leads' => now()->startOfMonth()->toDateString()]);

    $this->actingAs($admin->user);

    $rowAll = collect(Volt::test('investors.conversion-index')->instance()->monthlyRows())->last();
    expect($rowAll['lead'])->toBe(3);

    $rowScoped = collect(
        Volt::test('investors.conversion-index')
            ->set('viewAsEmployeeId', $foB->id)
            ->instance()
            ->monthlyRows()
    )->last();

    expect($rowScoped['lead'])->toBe(2);
});
