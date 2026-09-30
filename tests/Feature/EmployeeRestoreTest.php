<?php

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo(Permission::findOrCreate('access-internal-org'));
    $this->actingAs($this->admin);
});

test('deleting an employee soft deletes both the user and the employee profile', function () {
    $user = User::factory()->create();
    $employee = Employee::create(['user_id' => $user->id]);

    Volt::test('master.employees.index')
        ->call('confirmDelete', $user->id)
        ->call('delete');

    expect(User::withTrashed()->find($user->id)->trashed())->toBeTrue()
        ->and(Employee::withTrashed()->find($employee->id)->trashed())->toBeTrue();
});

test('deleted employees only show up in the trashed view', function () {
    $user = User::factory()->create(['name' => 'Karyawan Terhapus']);
    Employee::create(['user_id' => $user->id])->delete();
    $user->delete();

    Volt::test('master.employees.index')
        ->assertDontSee('Karyawan Terhapus')
        ->call('toggleTrashed')
        ->assertSee('Karyawan Terhapus')
        ->assertSee('Pulihkan');
});

test('restoring brings back the user and employee profile', function () {
    $user = User::factory()->create();
    $employee = Employee::create(['user_id' => $user->id]);
    $employee->delete();
    $user->delete();

    Volt::test('master.employees.index')
        ->call('restore', $user->id)
        ->assertHasNoErrors();

    expect(User::find($user->id))->not->toBeNull()
        ->and(Employee::find($employee->id))->not->toBeNull();
});

test('restoring a user that is not deleted fails', function () {
    $user = User::factory()->create();

    Volt::test('master.employees.index')->call('restore', $user->id);
})->throws(ModelNotFoundException::class);

test('users without the permission cannot open the employees page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('employees.index'))
        ->assertForbidden();
});
