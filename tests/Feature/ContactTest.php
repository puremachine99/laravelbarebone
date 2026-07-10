<?php

use App\Models\Contact;
use App\Models\ContactPosition;
use App\Models\Employee;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('contact page is rendered for authenticated users', function () {
    $user = User::factory()->create();
    $permission = Permission::findOrCreate('manage-pipeline');
    $user->givePermissionTo($permission);
    $this->actingAs($user);

    $response = $this->get(route('contact.index'));
    $response->assertOk();
});

test('can create contact via livewire volt component', function () {
    $user = User::factory()->create();
    $employee = Employee::create([
        'user_id' => $user->id,
    ]);

    $region = Region::create(['name' => 'Region Test', 'code' => 'RT-1']);
    $province = Province::create(['name' => 'Provinsi Test']);
    $regency = Regency::create(['name' => 'Kabupaten Test', 'province_id' => $province->id, 'region_id' => $region->id, 'type' => 'Kabupaten']);
    $position = ContactPosition::create(['name' => 'Staff', 'kelompok_jabatan' => 'JA', 'eselon' => 'IV.a', 'tingkat' => 'Pusat']);

    $permission = Permission::findOrCreate('manage-pipeline');
    $user->givePermissionTo($permission);
    $this->actingAs($user);

    Volt::test('partnership.contact-index')
        ->set('name', 'John Doe')
        ->set('sumber', 'SDS Umum')
        ->set('region_id', $region->id)
        ->set('category', 'Pemerintah Daerah')
        ->set('province_id', $province->id)
        ->set('regency_id', $regency->id)
        ->set('contact_position_id', $position->id)
        ->set('phone', '08123456789')
        ->set('email', 'johndoe@example.com')
        ->call('saveContact')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('pmo_contacts', [
        'name' => 'John Doe',
        'sumber' => 'SDS Umum',
        'region_id' => $region->id,
        'pic_employee_id' => $employee->id,
        'category' => 'Pemerintah Daerah',
        'regency_id' => $regency->id,
        'contact_position_id' => $position->id,
        'phone' => '08123456789',
        'email' => 'johndoe@example.com',
    ]);
});

test('can edit existing contact', function () {
    $user = User::factory()->create();
    $employee = Employee::create([
        'user_id' => $user->id,
    ]);

    $region = Region::create(['name' => 'Region Test', 'code' => 'RT-1']);
    $province = Province::create(['name' => 'Provinsi Test']);
    $regency = Regency::create(['name' => 'Kabupaten Test', 'province_id' => $province->id, 'region_id' => $region->id, 'type' => 'Kabupaten']);
    $position = ContactPosition::create(['name' => 'Direktur', 'kelompok_jabatan' => 'JPT', 'eselon' => 'II.a', 'tingkat' => 'Pusat']);

    $contact = Contact::create([
        'name' => 'Jane Doe',
        'sumber' => 'Referal',
        'region_id' => $region->id,
        'pic_employee_id' => $employee->id,
        'category' => 'Kementerian / Lembaga',
        'regency_id' => $regency->id,
        'contact_position_id' => $position->id,
        'phone' => '0821111111',
        'email' => 'janedoe@example.com',
    ]);

    $permission = Permission::findOrCreate('manage-pipeline');
    $user->givePermissionTo($permission);
    $this->actingAs($user);

    Volt::test('partnership.contact-index')
        ->call('editContact', $contact->id)
        ->assertSet('contact_id', $contact->id)
        ->assertSet('name', 'Jane Doe')
        ->assertSet('province_id', $province->id)
        ->set('name', 'Jane Smith')
        ->call('saveContact')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('pmo_contacts', [
        'id' => $contact->id,
        'name' => 'Jane Smith',
    ]);
});

test('can delete contact', function () {
    $user = User::factory()->create();
    $employee = Employee::create([
        'user_id' => $user->id,
    ]);

    $region = Region::create(['name' => 'Region Test', 'code' => 'RT-1']);
    $province = Province::create(['name' => 'Provinsi Test']);
    $regency = Regency::create(['name' => 'Kabupaten Test', 'province_id' => $province->id, 'region_id' => $region->id, 'type' => 'Kabupaten']);
    $position = ContactPosition::create(['name' => 'Staf', 'kelompok_jabatan' => 'JA', 'eselon' => 'IV.b', 'tingkat' => 'Pusat']);

    $contact = Contact::create([
        'name' => 'Delete Me',
        'sumber' => 'Referal',
        'region_id' => $region->id,
        'pic_employee_id' => $employee->id,
        'category' => 'Kementerian / Lembaga',
        'regency_id' => $regency->id,
        'contact_position_id' => $position->id,
        'phone' => '08211111112',
        'email' => 'deleteme@example.com',
    ]);

    $permission = Permission::findOrCreate('manage-pipeline');
    $user->givePermissionTo($permission);
    $this->actingAs($user);

    Volt::test('partnership.contact-index')
        ->call('deleteContact', $contact->id);

    $this->assertDatabaseMissing('pmo_contacts', [
        'id' => $contact->id,
    ]);
});
