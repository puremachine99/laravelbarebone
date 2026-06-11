<?php

use App\Models\Contact;
use App\Models\Employee;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Region;
use App\Models\User;
use Livewire\Volt\Volt;

test('contact page is rendered for authenticated users', function () {
    $user = User::factory()->create();
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
    $province = Province::create(['name' => 'Provinsi Test', 'region_id' => $region->id]);
    $regency = Regency::create(['name' => 'Kabupaten Test', 'province_id' => $province->id, 'type' => 'Kabupaten']);

    $this->actingAs($user);

    Volt::test('master.contact.index')
        ->set('name', 'John Doe')
        ->set('sumber', 'SDS Umum')
        ->set('region_id', $region->id)
        ->set('category', 'Pemerintah Daerah')
        ->set('regency_id', $regency->id)
        ->set('jabatan', 'Staff')
        ->set('phone', '08123456789')
        ->set('email', 'johndoe@example.com')
        ->call('saveContact')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('contacts', [
        'name' => 'John Doe',
        'sumber' => 'SDS Umum',
        'region_id' => $region->id,
        'pic_employee_id' => $employee->id,
        'category' => 'Pemerintah Daerah',
        'regency_id' => $regency->id,
        'jabatan' => 'Staff',
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
    $province = Province::create(['name' => 'Provinsi Test', 'region_id' => $region->id]);
    $regency = Regency::create(['name' => 'Kabupaten Test', 'province_id' => $province->id, 'type' => 'Kabupaten']);

    $contact = Contact::create([
        'name' => 'Jane Doe',
        'sumber' => 'Referal',
        'region_id' => $region->id,
        'pic_employee_id' => $employee->id,
        'category' => 'Kementerian / Lembaga',
        'regency_id' => $regency->id,
        'jabatan' => 'Direktur',
        'phone' => '08211111111',
        'email' => 'janedoe@example.com',
    ]);

    $this->actingAs($user);

    Volt::test('master.contact.index')
        ->call('editContact', $contact->id)
        ->assertSet('contact_id', $contact->id)
        ->assertSet('name', 'Jane Doe')
        ->set('name', 'Jane Smith')
        ->call('saveContact')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('contacts', [
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
    $province = Province::create(['name' => 'Provinsi Test', 'region_id' => $region->id]);
    $regency = Regency::create(['name' => 'Kabupaten Test', 'province_id' => $province->id, 'type' => 'Kabupaten']);

    $contact = Contact::create([
        'name' => 'Delete Me',
        'sumber' => 'Referal',
        'region_id' => $region->id,
        'pic_employee_id' => $employee->id,
        'category' => 'Kementerian / Lembaga',
        'regency_id' => $regency->id,
        'jabatan' => 'Staf',
        'phone' => '08211111112',
        'email' => 'deleteme@example.com',
    ]);

    $this->actingAs($user);

    Volt::test('master.contact.index')
        ->call('deleteContact', $contact->id);

    $this->assertDatabaseMissing('contacts', [
        'id' => $contact->id,
    ]);
});
