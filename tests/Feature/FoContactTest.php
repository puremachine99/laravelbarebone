<?php

use App\Models\Employee;
use App\Models\FoContact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('guests are redirected to login', function () {
    $this->get(route('investors.contacts'))
        ->assertRedirect(route('login'));
});

test('unauthorized users without access-investors permission cannot access fo contact page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('investors.contacts'))
        ->assertStatus(403);
});

test('fo contact page is rendered for authorized users', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(moduleAccess('access-investors'));

    $this->actingAs($user)
        ->get(route('investors.contacts'))
        ->assertOk();
});

test('a plain fo is always locked as the pic of their own contacts', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(moduleAccess('access-investors'));
    $ownEmployee = Employee::create(['user_id' => $user->id]);
    $this->actingAs($user);

    $otherEmployee = Employee::create(['user_id' => User::factory()->create()->id]);

    Volt::test('investors.contact-index')
        ->set('nama', 'Budi Santoso')
        ->set('profesi', 'Direktur PT ABC')
        ->set('no_telepon', '081234567890')
        ->set('employee_id', $otherEmployee->id) // attempt to assign someone else — should be ignored
        ->set('catatan', 'Prospek hangat')
        ->call('saveContact')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('fo_contacts', [
        'nama' => 'Budi Santoso',
        'profesi' => 'Direktur PT ABC',
        'no_telepon' => '081234567890',
        'employee_id' => $ownEmployee->id,
        'catatan' => 'Prospek hangat',
    ]);
});

test('admin fo can assign any fo as pic', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Investor Admin')->givePermissionTo(moduleAccess('access-investors')));
    $this->actingAs($user);

    $pic = Employee::create(['user_id' => User::factory()->create()->id]);

    Volt::test('investors.contact-index')
        ->set('nama', 'Budi Santoso')
        ->set('employee_id', $pic->id)
        ->call('saveContact')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('fo_contacts', [
        'nama' => 'Budi Santoso',
        'employee_id' => $pic->id,
    ]);
});

test('can edit existing fo contact', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(moduleAccess('access-investors'));
    $this->actingAs($user);

    $contact = FoContact::factory()->create(['nama' => 'Jane Doe']);

    Volt::test('investors.contact-index')
        ->call('editContact', $contact->id)
        ->assertSet('contact_id', $contact->id)
        ->assertSet('nama', 'Jane Doe')
        ->set('nama', 'Jane Smith')
        ->call('saveContact')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('fo_contacts', [
        'id' => $contact->id,
        'nama' => 'Jane Smith',
    ]);
});

test('can soft delete fo contact and track record remains', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(moduleAccess('access-investors'));
    $this->actingAs($user);

    $contact = FoContact::factory()->create(['nama' => 'Delete Me']);

    Volt::test('investors.contact-index')
        ->call('deleteContact', $contact->id);

    $this->assertDatabaseMissing('fo_contacts', [
        'id' => $contact->id,
        'deleted_at' => null,
    ]);

    expect(FoContact::withTrashed()->find($contact->id))->not->toBeNull();
});

test('nama is required to save a fo contact', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(moduleAccess('access-investors'));
    $this->actingAs($user);

    Volt::test('investors.contact-index')
        ->set('nama', '')
        ->call('saveContact')
        ->assertHasErrors(['nama']);
});

test('a plain fo only sees their own contacts in the list, admin fo sees everyone', function () {
    $fo = User::factory()->create();
    $fo->givePermissionTo(moduleAccess('access-investors'));
    $foEmployee = Employee::create(['user_id' => $fo->id]);

    $otherEmployee = Employee::create(['user_id' => User::factory()->create()->id]);

    FoContact::factory()->create(['nama' => 'Kontak Milik FO', 'employee_id' => $foEmployee->id]);
    FoContact::factory()->create(['nama' => 'Kontak Milik Orang Lain', 'employee_id' => $otherEmployee->id]);

    $this->actingAs($fo);

    Volt::test('investors.contact-index')
        ->assertSee('Kontak Milik FO')
        ->assertDontSee('Kontak Milik Orang Lain');

    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('Investor Admin')->givePermissionTo(moduleAccess('access-investors')));
    $this->actingAs($admin);

    Volt::test('investors.contact-index')
        ->assertSee('Kontak Milik FO')
        ->assertSee('Kontak Milik Orang Lain');
});

test('a plain fo cannot edit or delete another fo contact', function () {
    $fo = User::factory()->create();
    $fo->givePermissionTo(moduleAccess('access-investors'));
    Employee::create(['user_id' => $fo->id]);

    $otherEmployee = Employee::create(['user_id' => User::factory()->create()->id]);
    $otherContact = FoContact::factory()->create(['employee_id' => $otherEmployee->id]);

    $this->actingAs($fo);

    Volt::test('investors.contact-index')
        ->call('editContact', $otherContact->id)
        ->assertStatus(403);

    Volt::test('investors.contact-index')
        ->call('confirmDelete', $otherContact->id)
        ->assertStatus(403);

    $this->assertDatabaseHas('fo_contacts', ['id' => $otherContact->id]);
});
