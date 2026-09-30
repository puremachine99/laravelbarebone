<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\FoContact;
use App\Models\FoFunnel;
use App\Models\FoFunnelActivity;
use App\Models\FoTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

function makeDashboardFoEmployee(string $roleName): Employee
{
    $department = Department::firstOrCreate(['name' => 'Departemen Finance']);
    $user = User::factory()->create();
    $user->givePermissionTo(moduleAccess('access-investors'));
    $user->assignRole(Role::findOrCreate($roleName));

    return Employee::create(['user_id' => $user->id, 'department_id' => $department->id]);
}

test('guests are redirected to login', function () {
    $this->get(route('investors.index'))
        ->assertRedirect(route('login'));
});

test('unauthorized users without access-investors permission cannot access the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('investors.index'))
        ->assertStatus(403);
});

test('dashboard is rendered for authorized users', function () {
    $admin = makeDashboardFoEmployee('Investor Admin');
    $this->actingAs($admin->user);

    Volt::test('investors.dashboard-index')
        ->assertOk()
        ->assertSee('Daily Dashboard')
        ->assertSee('Realization per FO')
        ->assertSee('FO Leaderboard');
});

test('a plain funding officer is locked to their own data with no masuk sebagai selector', function () {
    $fo = makeDashboardFoEmployee('Funding Officer');
    $this->actingAs($fo->user);

    $component = Volt::test('investors.dashboard-index');

    $component->assertOk()->assertSee($fo->user->name);

    expect($component->get('viewAsEmployeeId'))->toBe($fo->id);
});

test('todays lead baru realisasi is counted correctly per fo and aggregated for the team', function () {
    $admin = makeDashboardFoEmployee('Investor Admin');
    $foA = makeDashboardFoEmployee('Funding Officer');
    $foB = makeDashboardFoEmployee('Funding Officer');

    $contactA = FoContact::factory()->create(['employee_id' => $foA->id]);
    $contactB = FoContact::factory()->create(['employee_id' => $foB->id]);

    // FO A: 2 leads today, 1 yesterday (should not count towards today's total).
    FoFunnel::factory()->create(['fo_contact_id' => $contactA->id, 'employee_id' => $foA->id, 'tgl_leads' => now()->toDateString()]);
    FoFunnel::factory()->create(['fo_contact_id' => $contactA->id, 'employee_id' => $foA->id, 'tgl_leads' => now()->toDateString()]);
    FoFunnel::factory()->create(['fo_contact_id' => $contactA->id, 'employee_id' => $foA->id, 'tgl_leads' => now()->subDay()->toDateString()]);

    // FO B: 1 lead today, also qualified today.
    FoFunnel::factory()->create([
        'fo_contact_id' => $contactB->id,
        'employee_id' => $foB->id,
        'tgl_leads' => now()->toDateString(),
        'tgl_qualified' => now()->toDateString(),
        'stage' => 'ql',
    ]);

    $this->actingAs($admin->user);

    $component = Volt::test('investors.dashboard-index')->instance();

    $summary = $component->todaySummary();

    expect($summary['leads']['realisasi'])->toBe(3)
        ->and($summary['ql']['realisasi'])->toBe(1);

    $rows = collect($component->realisasiPerFo())->keyBy('name');

    expect($rows[$foA->user->name]['leads'])->toBe(2)
        ->and($rows[$foB->user->name]['leads'])->toBe(1)
        ->and($rows[$foB->user->name]['ql'])->toBe(1);
});

test('closing realisasi and nilai closing only count funds closing or akad today', function () {
    $admin = makeDashboardFoEmployee('Investor Admin');
    $fo = makeDashboardFoEmployee('Funding Officer');
    $contact = FoContact::factory()->create(['employee_id' => $fo->id]);

    FoFunnel::factory()->create([
        'fo_contact_id' => $contact->id,
        'employee_id' => $fo->id,
        'stage' => 'closing',
        'tgl_leads' => now()->subDays(3)->toDateString(),
        'tgl_closing' => now()->toDateString(),
        'nominal_investasi' => 500_000_000,
    ]);

    // Won is just a verbal commitment — should not count as closing.
    FoFunnel::factory()->create([
        'fo_contact_id' => $contact->id,
        'employee_id' => $fo->id,
        'stage' => 'won',
        'tgl_leads' => now()->subDays(3)->toDateString(),
        'tgl_closing' => now()->toDateString(),
        'nominal_investasi' => 999_000_000,
    ]);

    // Lost today should not count as closing.
    FoFunnel::factory()->create([
        'fo_contact_id' => $contact->id,
        'employee_id' => $fo->id,
        'stage' => 'lost',
        'tgl_leads' => now()->subDays(3)->toDateString(),
        'tgl_closing' => now()->toDateString(),
    ]);

    $this->actingAs($admin->user);

    $component = Volt::test('investors.dashboard-index')->instance();
    $summary = $component->todaySummary();

    expect($summary['closing']['realisasi'])->toBe(1)
        ->and((float) $summary['nilai_closing']['realisasi'])->toBe(500_000_000.0);
});

test('daily target is derived from yearly target divided by 12 months and 25 working days', function () {
    $admin = makeDashboardFoEmployee('Investor Admin');
    $fo = makeDashboardFoEmployee('Funding Officer');

    FoTarget::factory()->create([
        'employee_id' => $fo->id,
        'metric_key' => 'leads',
        'yearly_target' => 300,
    ]);

    $this->actingAs($admin->user);

    $summary = Volt::test('investors.dashboard-index')->instance()->todaySummary();

    // 300 / 12 / 25 = 1 lead/day.
    expect((float) $summary['leads']['target'])->toBe(1.0);
});

test('masuk sebagai selector scopes the dashboard down to a single fo', function () {
    $admin = makeDashboardFoEmployee('Investor Admin');
    $foA = makeDashboardFoEmployee('Funding Officer');
    $foB = makeDashboardFoEmployee('Funding Officer');

    $contactA = FoContact::factory()->create(['employee_id' => $foA->id]);
    $contactB = FoContact::factory()->create(['employee_id' => $foB->id]);

    FoFunnel::factory()->create(['fo_contact_id' => $contactA->id, 'employee_id' => $foA->id, 'tgl_leads' => now()->toDateString()]);
    FoFunnel::factory()->create(['fo_contact_id' => $contactB->id, 'employee_id' => $foB->id, 'tgl_leads' => now()->toDateString()]);
    FoFunnel::factory()->create(['fo_contact_id' => $contactB->id, 'employee_id' => $foB->id, 'tgl_leads' => now()->toDateString()]);

    $this->actingAs($admin->user);

    $component = Volt::test('investors.dashboard-index')
        ->set('viewAsEmployeeId', $foB->id)
        ->instance();

    $summary = $component->todaySummary();

    expect($summary['leads']['realisasi'])->toBe(2);

    $rows = collect($component->realisasiPerFo());
    expect($rows)->toHaveCount(1)
        ->and($rows->first()['name'])->toBe($foB->user->name);
});

test('leaderboard is sorted by cumulative nilai closing regardless of masuk sebagai scope', function () {
    $admin = makeDashboardFoEmployee('Investor Admin');
    $foLow = makeDashboardFoEmployee('Funding Officer');
    $foHigh = makeDashboardFoEmployee('Funding Officer');

    $contactLow = FoContact::factory()->create(['employee_id' => $foLow->id]);
    $contactHigh = FoContact::factory()->create(['employee_id' => $foHigh->id]);

    FoFunnel::factory()->create([
        'fo_contact_id' => $contactLow->id,
        'employee_id' => $foLow->id,
        'stage' => 'closing',
        'tgl_closing' => now()->toDateString(),
        'nominal_investasi' => 100_000_000,
    ]);

    FoFunnel::factory()->create([
        'fo_contact_id' => $contactHigh->id,
        'employee_id' => $foHigh->id,
        'stage' => 'closing',
        'tgl_closing' => now()->toDateString(),
        'nominal_investasi' => 900_000_000,
    ]);

    $this->actingAs($admin->user);

    $leaderboard = Volt::test('investors.dashboard-index')
        ->set('viewAsEmployeeId', $foLow->id)
        ->instance()
        ->leaderboard();

    expect($leaderboard[0]['name'])->toBe($foHigh->user->name)
        ->and($leaderboard[1]['name'])->toBe($foLow->user->name);
});

test('leaderboard only counts deals closed in the selected month, not all-time', function () {
    $admin = makeDashboardFoEmployee('Investor Admin');
    $fo = makeDashboardFoEmployee('Funding Officer');
    $contact = FoContact::factory()->create(['employee_id' => $fo->id]);

    FoFunnel::factory()->create([
        'fo_contact_id' => $contact->id,
        'employee_id' => $fo->id,
        'stage' => 'closing',
        'tgl_closing' => now()->subMonths(3)->toDateString(),
        'nominal_investasi' => 900_000_000,
    ]);

    $this->actingAs($admin->user);

    $leaderboard = collect(Volt::test('investors.dashboard-index')->instance()->leaderboard())->keyBy('name');

    expect($leaderboard[$fo->user->name]['closing'])->toBe(0)
        ->and($leaderboard[$fo->user->name]['nilai_closing'])->toBe(0.0);
});

test('reset filter button restores the default month, day and view-as scope', function () {
    $admin = makeDashboardFoEmployee('Investor Admin');
    $fo = makeDashboardFoEmployee('Funding Officer');
    $this->actingAs($admin->user);

    $component = Volt::test('investors.dashboard-index')
        ->set('selectedMonth', 1)
        ->set('selectedYear', now()->year - 1)
        ->set('selectedDay', 15)
        ->set('viewAsEmployeeId', $fo->id)
        ->call('resetFilters');

    expect($component->get('selectedMonth'))->toBe((int) now()->month)
        ->and($component->get('selectedYear'))->toBe((int) now()->year)
        ->and($component->get('selectedDay'))->toBe((int) now()->day)
        ->and($component->get('viewAsEmployeeId'))->toBeNull();
});

test('selectedDate defaults to today', function () {
    $admin = makeDashboardFoEmployee('Investor Admin');
    $this->actingAs($admin->user);

    $component = Volt::test('investors.dashboard-index');

    expect($component->get('selectedYear'))->toBe((int) now()->year)
        ->and($component->get('selectedMonth'))->toBe((int) now()->month)
        ->and($component->get('selectedDay'))->toBe((int) now()->day)
        ->and($component->instance()->selectedDate())->toBe(now()->toDateString());
});

test('clicking a day in the Activity Tracker drills down realisasi to that day', function () {
    $admin = makeDashboardFoEmployee('Investor Admin');
    $fo = makeDashboardFoEmployee('Funding Officer');
    $contact = FoContact::factory()->create(['employee_id' => $fo->id]);

    // Guaranteed to stay within the current month regardless of when the test runs.
    $otherDay = now()->day > 3 ? now()->subDays(3) : now()->addDays(3);

    FoFunnel::factory()->create(['fo_contact_id' => $contact->id, 'employee_id' => $fo->id, 'tgl_leads' => now()->toDateString()]);
    FoFunnel::factory()->count(2)->create(['fo_contact_id' => $contact->id, 'employee_id' => $fo->id, 'tgl_leads' => $otherDay->toDateString()]);

    $this->actingAs($admin->user);

    $component = Volt::test('investors.dashboard-index');

    expect($component->instance()->todaySummary()['leads']['realisasi'])->toBe(1);

    $component->call('selectDay', $otherDay->day);

    $rows = collect($component->instance()->realisasiPerFo())->keyBy('name');

    expect($component->instance()->todaySummary()['leads']['realisasi'])->toBe(2)
        ->and($rows[$fo->user->name]['leads'])->toBe(2);
});

test('monthly trend covers the whole month of the selected date, not always the current month', function () {
    $admin = makeDashboardFoEmployee('Investor Admin');
    $fo = makeDashboardFoEmployee('Funding Officer');
    $contact = FoContact::factory()->create(['employee_id' => $fo->id]);

    $pastMonth = now()->subMonths(2)->startOfMonth()->addDays(4);
    $selectedDate = $pastMonth->toDateString();

    // This lead sits in a month far in the past — should only show up if the trend
    // actually follows the selected month, not always the current month.
    FoFunnel::factory()->create([
        'fo_contact_id' => $contact->id,
        'employee_id' => $fo->id,
        'tgl_leads' => $selectedDate,
    ]);

    $this->actingAs($admin->user);

    $component = Volt::test('investors.dashboard-index')
        ->set('selectedYear', $pastMonth->year)
        ->set('selectedMonth', $pastMonth->month)
        ->set('selectedDay', $pastMonth->day);

    $trend = collect($component->instance()->monthlyTrend());

    expect($trend)->toHaveCount($pastMonth->daysInMonth)
        ->and($trend[4]['leads'])->toBe(1)
        ->and($trend->sum('leads'))->toBe(1);
});

test('daily target year is derived from the selected date, not the current date', function () {
    $admin = makeDashboardFoEmployee('Investor Admin');
    $fo = makeDashboardFoEmployee('Funding Officer');

    FoTarget::factory()->create([
        'employee_id' => $fo->id,
        'metric_key' => 'leads',
        'year' => now()->year - 1,
        'yearly_target' => 1200,
    ]);

    $this->actingAs($admin->user);

    $component = Volt::test('investors.dashboard-index')
        ->set('selectedYear', now()->subYear()->year);

    // 1200 / 12 / 25 = 4 leads/day, only visible when selectedYear falls in last year.
    expect((float) $component->instance()->todaySummary()['leads']['target'])->toBe(4.0);
});

test('pitching and follow up count distinct funnels once per day even with multiple activity logs', function () {
    $admin = makeDashboardFoEmployee('Investor Admin');
    $fo = makeDashboardFoEmployee('Funding Officer');
    $contact = FoContact::factory()->create(['employee_id' => $fo->id]);

    $funnel = FoFunnel::factory()->create([
        'fo_contact_id' => $contact->id,
        'employee_id' => $fo->id,
        'stage' => 'pitching',
    ]);

    FoFunnelActivity::factory()->create([
        'fo_funnel_id' => $funnel->id,
        'stage' => 'pitching',
        'tanggal' => now()->toDateString(),
    ]);
    FoFunnelActivity::factory()->create([
        'fo_funnel_id' => $funnel->id,
        'stage' => 'pitching',
        'tanggal' => now()->toDateString(),
    ]);

    $this->actingAs($admin->user);

    $summary = Volt::test('investors.dashboard-index')->instance()->todaySummary();

    expect($summary['pitching']['realisasi'])->toBe(1);
});

test('activity tracker heatmap renders and buckets counts by day of the selected month', function () {
    $admin = makeDashboardFoEmployee('Investor Admin');
    $fo = makeDashboardFoEmployee('Funding Officer');
    $contact = FoContact::factory()->create(['employee_id' => $fo->id]);

    $day5 = now()->startOfMonth()->addDays(4)->toDateString();
    $day10 = now()->startOfMonth()->addDays(9)->toDateString();

    FoFunnel::factory()->create(['fo_contact_id' => $contact->id, 'employee_id' => $fo->id, 'tgl_leads' => $day5]);
    FoFunnel::factory()->create(['fo_contact_id' => $contact->id, 'employee_id' => $fo->id, 'tgl_leads' => $day5]);
    FoFunnel::factory()->create(['fo_contact_id' => $contact->id, 'employee_id' => $fo->id, 'tgl_leads' => $day10]);

    FoFunnel::factory()->create([
        'fo_contact_id' => $contact->id,
        'employee_id' => $fo->id,
        'stage' => 'won',
        'tgl_leads' => $day5,
        'tgl_won' => $day10,
    ]);

    $this->actingAs($admin->user);

    $component = Volt::test('investors.dashboard-index')->assertSee('Activity Tracker');

    $heatmap = $component->instance()->activityHeatmap();
    $leadsRow = collect($heatmap['rows'])->firstWhere('key', 'leads');
    $wonRow = collect($heatmap['rows'])->firstWhere('key', 'won');

    expect($leadsRow['counts'][5])->toBe(3)
        ->and($leadsRow['counts'][10])->toBe(1)
        ->and($leadsRow['total'])->toBe(4)
        ->and($wonRow['counts'][10])->toBe(1)
        ->and($wonRow['total'])->toBe(1);
});
