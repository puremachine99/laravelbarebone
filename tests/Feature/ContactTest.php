<?php

use App\Enums\OpdJenis;
use App\Models\Contact;
use App\Models\ContactPosition;
use App\Models\Employee;
use App\Models\Opd;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

test('contact page is rendered for authenticated users', function () {
    $user = User::factory()->create();
    $permission = moduleAccess('access-partnership');
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

    $permission = moduleAccess('access-partnership');
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

    $permission = moduleAccess('access-partnership');
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

    $permission = moduleAccess('access-partnership');
    $user->givePermissionTo($permission);
    $this->actingAs($user);

    Volt::test('partnership.contact-index')
        ->call('deleteContact', $contact->id);

    $this->assertDatabaseMissing('pmo_contacts', [
        'id' => $contact->id,
    ]);
});

test('can create a Kementerian contact without provinsi or kabupaten/kota', function () {
    $user = User::factory()->create();
    Employee::create(['user_id' => $user->id]);
    $region = Region::create(['name' => 'Region Tiga', 'code' => 'REG-3']);
    $kementerian = Opd::factory()->kementerian()->create(['name' => 'Kementerian Keuangan', 'region_id' => $region->id]);
    $user->givePermissionTo(moduleAccess('access-partnership'));
    $this->actingAs($user);

    $component = Volt::test('partnership.contact-index')
        ->set('name', 'Jane Doe')
        ->set('sumber', 'SDS Umum')
        ->set('region_id', $region->id)
        ->set('category', 'Kementerian / Lembaga')
        ->set('contact_position_id', ContactPosition::create(['name' => 'Staff', 'kelompok_jabatan' => 'JA', 'eselon' => 'IV.a', 'tingkat' => 'Pusat'])->id)
        ->set('province_id', OpdJenis::Kementerian->value);

    expect($component->get('opdOptions')->pluck('id')->all())->toBe([$kementerian->id])
        ->and($component->get('regencyOptions'))->toBeEmpty();

    $component->call('saveContact')->assertHasErrors(['opd_id' => 'required']);

    $component->set('opd_id', $kementerian->id)->call('saveContact')->assertHasNoErrors();

    $contact = Contact::where('name', 'Jane Doe')->sole();

    expect($contact->opd_id)->toBe($kementerian->id)
        ->and($contact->regency_id)->toBeNull();

    $component->call('editContact', $contact->id);

    expect($component->get('province_id'))->toBe(OpdJenis::Kementerian->value);
});
