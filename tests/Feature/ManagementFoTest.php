<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\FoContact;
use App\Models\FoFunnel;
use App\Models\FoFunnelActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeFoEmployee(string $roleName): Employee
{
    $department = Department::firstOrCreate(['name' => 'Departemen Finance']);
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $user->assignRole(Role::findOrCreate($roleName));

    return Employee::create(['user_id' => $user->id, 'department_id' => $department->id]);
}

test('guests are redirected to login', function () {
    $this->get(route('investors.management-fo'))
        ->assertRedirect(route('login'));
});

test('a plain funding officer cannot access management fo', function () {
    $employee = makeFoEmployee('Funding Officer');
    $this->actingAs($employee->user);

    Volt::test('investors.management-fo')
        ->assertStatus(403);
});

test('investor admin can access management fo and sees the fo list', function () {
    $admin = makeFoEmployee('Investor Admin');
    makeFoEmployee('Funding Officer');
    $this->actingAs($admin->user);

    Volt::test('investors.management-fo')
        ->assertOk()
        ->assertSee('Management FO')
        ->assertSee('Leads')
        ->assertSee('Nilai Closing');
});

test('super admin can access management fo', function () {
    $admin = makeFoEmployee('Super Admin');
    $this->actingAs($admin->user);

    $this->get(route('investors.management-fo'))->assertOk();
});

test('updating a yearly target overrides the dummy value and recalculates the split', function () {
    $admin = makeFoEmployee('Investor Admin');
    $this->actingAs($admin->user);

    $component = Volt::test('investors.management-fo')
        ->set('activePeriod', 'tahunan')
        ->call('updateYearlyTarget', $admin->id, 'leads', 120)
        ->assertHasNoErrors();

    expect((float) $component->get('targetOverrides')[$admin->id]['leads'])->toBe(120.0);

    $component->set('activePeriod', 'bulanan');

    expect((float) $component->instance()->targetFor($admin->id, 'leads'))->toBe(10.0);
});

test('updating a yearly target rejects a negative value', function () {
    $admin = makeFoEmployee('Investor Admin');
    $this->actingAs($admin->user);

    $component = Volt::test('investors.management-fo')
        ->call('updateYearlyTarget', $admin->id, 'leads', -5)
        ->assertHasNoErrors();

    expect($component->get('targetOverrides'))->toBe([]);
});

test('realisasi leads and ql are counted from real funnel data within the active period', function () {
    $admin = makeFoEmployee('Investor Admin');
    $this->actingAs($admin->user);

    FoFunnel::factory()->create(['employee_id' => $admin->id, 'tgl_leads' => now()->toDateString(), 'tgl_qualified' => null]);
    FoFunnel::factory()->create(['employee_id' => $admin->id, 'tgl_leads' => now()->toDateString(), 'tgl_qualified' => now()->toDateString()]);
    FoFunnel::factory()->create(['employee_id' => $admin->id, 'tgl_leads' => now()->subYear()->toDateString()]);

    $component = Volt::test('investors.management-fo')
        ->set('activePeriod', 'tahunan');

    expect($component->instance()->realisasiFor($admin->id, 'leads'))->toBe(2)
        ->and($component->instance()->realisasiFor($admin->id, 'ql'))->toBe(1);
});

test('realisasi closing and nilai closing only count won/akad funnels closed within the period', function () {
    $admin = makeFoEmployee('Investor Admin');
    $this->actingAs($admin->user);

    FoFunnel::factory()->create([
        'employee_id' => $admin->id,
        'stage' => 'won',
        'tgl_closing' => now()->toDateString(),
        'nominal_investasi' => 500_000_000,
    ]);
    FoFunnel::factory()->create([
        'employee_id' => $admin->id,
        'stage' => 'akad',
        'tgl_closing' => now()->toDateString(),
        'nominal_investasi' => 250_000_000,
    ]);
    // Bukan won/akad — gak boleh ikut kehitung.
    FoFunnel::factory()->create([
        'employee_id' => $admin->id,
        'stage' => 'pitching',
        'tgl_closing' => now()->toDateString(),
        'nominal_investasi' => 999_000_000,
    ]);

    $component = Volt::test('investors.management-fo')
        ->set('activePeriod', 'tahunan');

    expect($component->instance()->realisasiFor($admin->id, 'closing'))->toBe(2)
        ->and($component->instance()->realisasiFor($admin->id, 'nilai_closing'))->toBe(750_000_000.0);
});

test('proyeksi counts funnels still in pipeline with a proyeksi close date in the period', function () {
    $admin = makeFoEmployee('Investor Admin');
    $this->actingAs($admin->user);

    FoFunnel::factory()->create([
        'employee_id' => $admin->id,
        'stage' => 'pitching',
        'proyeksi_close_date' => now()->toDateString(),
        'proyeksi_amount' => 300_000_000,
    ]);
    FoFunnel::factory()->create([
        'employee_id' => $admin->id,
        'stage' => 'followup',
        'proyeksi_close_date' => now()->toDateString(),
        'proyeksi_amount' => 200_000_000,
    ]);
    // Udah lost — gak boleh ikut jadi proyeksi.
    FoFunnel::factory()->create([
        'employee_id' => $admin->id,
        'stage' => 'lost',
        'proyeksi_close_date' => now()->toDateString(),
        'proyeksi_amount' => 999_000_000,
    ]);

    $component = Volt::test('investors.management-fo')
        ->set('activePeriod', 'tahunan');

    expect($component->instance()->proyeksiFor($admin->id, 'closing'))->toBe(2)
        ->and($component->instance()->proyeksiFor($admin->id, 'nilai_closing'))->toBe(500_000_000.0)
        ->and($component->instance()->proyeksiFor($admin->id, 'leads'))->toBeNull();
});

test('realisasi pitching counts distinct funnels with a pitching activity in the period', function () {
    $admin = makeFoEmployee('Investor Admin');
    $this->actingAs($admin->user);

    $contact = FoContact::factory()->create(['employee_id' => $admin->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $admin->id, 'fo_contact_id' => $contact->id, 'stage' => 'pitching']);

    // Dua aktivitas pitching di funnel yang SAMA — cuma dihitung 1 funnel, bukan 2.
    FoFunnelActivity::factory()->create(['fo_funnel_id' => $funnel->id, 'stage' => 'pitching', 'tanggal' => now()->toDateString()]);
    FoFunnelActivity::factory()->create(['fo_funnel_id' => $funnel->id, 'stage' => 'pitching', 'tanggal' => now()->toDateString()]);

    $component = Volt::test('investors.management-fo')
        ->set('activePeriod', 'tahunan');

    expect($component->instance()->realisasiFor($admin->id, 'pitching'))->toBe(1);
});
