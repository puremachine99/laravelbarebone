<?php

use App\Models\Province;
use App\Models\Regency;
use App\Models\Region;
use App\Models\User;
use App\Support\ResourcePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    ResourcePermissions::ensureExist();

    $user = User::factory()->create();
    $user->givePermissionTo(Permission::all());
    $this->actingAs($user);

    $region = Region::factory()->create(['name' => 'Wilayah Satu']);
    $this->province = Province::factory()->create(['name' => 'Prov. Contoh']);

    Regency::factory()->create(['province_id' => $this->province->id, 'name' => 'Prov. Contoh', 'type' => 'Provinsi', 'region_id' => $region->id]);
    Regency::factory()->create(['province_id' => $this->province->id, 'name' => 'Kab. Satu', 'type' => 'Kabupaten', 'region_id' => $region->id]);
    Regency::factory()->create(['province_id' => $this->province->id, 'name' => 'Kota Dua', 'type' => 'Kota', 'region_id' => $region->id]);
});

it('counts only kabupaten and kota per province', function () {
    $province = Volt::test('master.region.index')->get('provinces')->items()[0];

    expect($province->regencies_count)->toBe(2);
});

it('shows the region of each kabupaten/kota', function () {
    Volt::test('master.region.index')
        ->assertSee('Kab. Satu')
        ->assertSee('Wilayah Satu');
});

it('lists every region found among the regencies of a province', function () {
    $other = Region::factory()->create(['name' => 'Wilayah Dua']);
    Regency::factory()->create(['province_id' => $this->province->id, 'name' => 'Kab. Tiga', 'type' => 'Kabupaten', 'region_id' => $other->id]);

    Volt::test('master.region.index')
        ->assertSeeInOrder(['Prov. Contoh', 'Wilayah Satu', 'Wilayah Dua']);
});
