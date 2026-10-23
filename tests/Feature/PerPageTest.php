<?php

use App\Livewire\Admin\RoleManagement;
use App\Models\User;
use App\Support\PerPage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('per page only accepts the allowed options and never "show all"', function () {
    expect(PerPage::OPTIONS)->toBe([10, 30, 50, 100]);

    foreach (PerPage::OPTIONS as $option) {
        expect(PerPage::resolve((string) $option))->toBe($option);
    }

    expect(PerPage::resolve(5))->toBe(10)
        ->and(PerPage::resolve('all'))->toBe(10)
        ->and(PerPage::resolve(-1))->toBe(10)
        ->and(PerPage::resolve(100000))->toBe(10)
        ->and(PerPage::resolve(null))->toBe(10);
});

test('role management paginates by the selected per page', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(moduleAccess('access-admin'));

    Livewire\Livewire::actingAs($user)
        ->test(RoleManagement::class)
        ->assertSet('perPage', 10)
        ->set('perPage', 50)
        ->assertSet('perPage', 50)
        ->set('perPage', 7)
        ->assertSet('perPage', 10);
});
