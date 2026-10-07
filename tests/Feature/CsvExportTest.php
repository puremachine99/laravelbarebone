<?php

use App\Models\Contact;
use App\Models\Employee;
use App\Models\Opd;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('authorized user can download the csv export for a registered page', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(moduleAccess('access-partnership'));

    Opd::factory()->create(['name' => 'Dinas Kesehatan']);

    $response = $this->actingAs($user)->get(route('csv-export.download', 'opd'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->assertDownload();

    expect($response->streamedContent())->toContain('Dinas Kesehatan');
});

test('the response reports total row count upfront for client-side progress tracking', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(moduleAccess('access-partnership'));

    Opd::factory()->count(3)->create();

    $this->actingAs($user)
        ->get(route('csv-export.download', 'opd'))
        ->assertHeader('X-Csv-Total-Rows', '3');
});

test('user without the required permission cannot download the csv export', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('csv-export.download', 'opd'))
        ->assertForbidden();
});

test('an unknown export slug returns a 404', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(moduleAccess('access-partnership'));

    $this->actingAs($user)
        ->get(route('csv-export.download', 'not-a-real-export'))
        ->assertNotFound();
});

test('the export button only appears on pages registered for csv export', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(moduleAccess('access-partnership'));

    $this->actingAs($user)
        ->get(route('opd.index'))
        ->assertOk()
        ->assertSee('Export OPD (CSV)', false);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Export OPD (CSV)', false);
});

test('a regular pmo user exporting contacts only gets their own contacts, not other pics', function () {
    $user = User::factory()->create();

    $ownEmployee = Employee::create(['user_id' => $user->id]);
    $otherEmployee = Employee::create(['user_id' => User::factory()->create()->id]);

    // Granting the permission must come after Employee::create(), since EmployeeObserver
    // re-syncs (and can revoke) module permissions based on the employee's department.
    $user->givePermissionTo(moduleAccess('access-partnership'));

    Contact::create([
        'name' => 'Kontak Milik Sendiri',
        'sumber' => 'SDS Umum',
        'category' => 'Non Pemerintah',
        'pic_employee_id' => $ownEmployee->id,
    ]);

    Contact::create([
        'name' => 'Kontak Milik PMO Lain',
        'sumber' => 'SDS Umum',
        'category' => 'Non Pemerintah',
        'pic_employee_id' => $otherEmployee->id,
    ]);

    $response = $this->actingAs($user)->get(route('csv-export.download', 'contacts'));

    $content = $response->streamedContent();

    expect($content)->toContain('Kontak Milik Sendiri')
        ->and($content)->not->toContain('Kontak Milik PMO Lain');
});

test('the region page exports all data from the navbar and each tab exports only its own data', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(moduleAccess('access-partnership'));

    $region = Region::factory()->create(['name' => 'Wilayah Satu', 'code' => 'REG-1']);
    $province = Province::factory()->create(['name' => 'Prov. Contoh']);
    Regency::factory()->create(['province_id' => $province->id, 'name' => 'Prov. Contoh', 'type' => 'Provinsi', 'region_id' => $region->id]);
    Regency::factory()->create(['province_id' => $province->id, 'name' => 'Kab. Satu', 'type' => 'Kabupaten', 'region_id' => $region->id]);

    $all = $this->actingAs($user)->get(route('csv-export.download', 'region'))->streamedContent();
    expect($all)->toContain('Kab. Satu')->toContain('Prov. Contoh')->toContain('Wilayah Satu');

    $provinces = $this->actingAs($user)->get(route('csv-export.download', 'region-provinces'))->streamedContent();
    expect($provinces)->toContain('"Prov. Contoh","Wilayah Satu",1')->not->toContain('Kab. Satu');

    $regencies = $this->actingAs($user)->get(route('csv-export.download', 'region-regencies'))->streamedContent();
    expect($regencies)->toContain('Kab. Satu');

    $regions = $this->actingAs($user)->get(route('csv-export.download', 'region-regions'))->streamedContent();
    expect($regions)->toContain('REG-1,"Wilayah Satu",1')->not->toContain('Kab. Satu');

    $this->actingAs($user)->get(route('region.index'))
        ->assertOk()
        ->assertSee('Export Semua Data Lokasi (CSV)', false)
        ->assertSee('Export Region (CSV)', false)
        ->assertSee('Export Provinsi (CSV)', false)
        ->assertSee('Export Kabupaten / Kota (CSV)', false);
});
