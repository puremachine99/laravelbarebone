<?php

use App\Models\Opd;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Region;
use App\Models\User;
use App\Support\ResourcePermissions;
use Database\Seeders\DataMasterInstansiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->region = Region::factory()->create(['name' => 'Wilayah Satu']);
    $province = Province::factory()->create(['name' => 'Prov. Aceh']);

    $this->regency = Regency::factory()->create([
        'province_id' => $province->id,
        'name' => 'Prov. Aceh',
        'type' => 'Provinsi',
        'region_id' => $this->region->id,
    ]);
});

it('sets jenis on existing OPDs and creates the missing ones from the CSV', function () {
    $existing = Opd::factory()->create(['regency_id' => $this->regency->id, 'name' => 'Badan Kepegawaian Aceh']);

    $this->seed(DataMasterInstansiSeeder::class);

    expect($existing->fresh()->jenis)->toBe('OPD')
        ->and(Opd::where('regency_id', $this->regency->id)->count())->toBeGreaterThan(1)
        ->and(Opd::whereNull('jenis')->count())->toBe(0);
});

it('does not duplicate OPDs when the seeder runs twice', function () {
    $this->seed(DataMasterInstansiSeeder::class);
    $count = Opd::count();

    $this->seed(DataMasterInstansiSeeder::class);

    expect(Opd::count())->toBe($count);
});

it('filters OPDs by jenis and region', function () {
    $user = User::factory()->create();
    ResourcePermissions::ensureExist();
    $user->givePermissionTo(Permission::all());
    $this->actingAs($user);

    Opd::factory()->create(['regency_id' => $this->regency->id, 'name' => 'Dinas Alpha', 'jenis' => 'OPD']);
    Opd::factory()->create(['regency_id' => $this->regency->id, 'name' => 'Rumah Sakit Beta', 'jenis' => 'Unit Kesehatan']);

    Volt::test('master.opd.index')
        ->set('filterJenis', 'Unit Kesehatan')
        ->assertSee('Rumah Sakit Beta')
        ->assertDontSee('Dinas Alpha')
        ->set('filterJenis', '')
        ->set('filterRegion', $this->region->id)
        ->assertSee('Dinas Alpha')
        ->set('filterRegion', $this->region->id + 99)
        ->assertDontSee('Dinas Alpha');
});
