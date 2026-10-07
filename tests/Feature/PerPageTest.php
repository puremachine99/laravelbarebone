<?php

use App\Livewire\Admin\RoleManagement;
use App\Models\Region;
use App\Models\User;
use App\Support\PerPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

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

test('region table paginates by the selected per page and ignores tampered values', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(moduleAccess('access-partnership'));

    Region::factory()->count(35)->create();

    $component = Volt::actingAs($user)->test('master.region.index');

    expect($component->get('regions')->perPage())->toBe(10);

    $component->assertSee('Baris')->assertSeeHtml('wire:model.live="perPage"');

    $component->set('perPage', 30);
    expect($component->get('regions')->perPage())->toBe(30);

    $component->set('perPage', 100);
    expect($component->get('regions')->perPage())->toBe(100)
        ->and($component->get('regions')->count())->toBe(35);

    $component->set('perPage', 99999);
    expect($component->get('regions')->perPage())->toBe(10);
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
