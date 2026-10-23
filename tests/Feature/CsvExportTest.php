<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authorized user can download the csv export for a registered page', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(moduleAccess('access-internal-org'));

    User::factory()->create(['name' => 'Budi Santoso']);

    $response = $this->actingAs($user)->get(route('csv-export.download', 'employees'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->assertDownload();

    expect($response->streamedContent())->toContain('Budi Santoso');
});

test('the response reports total row count upfront for client-side progress tracking', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(moduleAccess('access-internal-org'));

    User::factory()->count(2)->create();

    $this->actingAs($user)
        ->get(route('csv-export.download', 'employees'))
        ->assertHeader('X-Csv-Total-Rows', '3');
});

test('user without the required permission cannot download the csv export', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('csv-export.download', 'employees'))
        ->assertForbidden();
});

test('an unknown export slug returns a 404', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(moduleAccess('access-internal-org'));

    $this->actingAs($user)
        ->get(route('csv-export.download', 'not-a-real-export'))
        ->assertNotFound();
});

test('the export button only appears on pages registered for csv export', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(moduleAccess('access-internal-org'));

    $this->actingAs($user)
        ->get(route('employees.index'))
        ->assertOk()
        ->assertSee('Export Karyawan (CSV)', false);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Export Karyawan (CSV)', false);
});
