<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;

beforeEach(function () {
    config(['ai_center.data_api.token' => 'nkm_service-token']);

    $finance = Department::create(['name' => 'Departemen Finance']);
    $it = Department::create(['name' => 'Departemen IT']);
    $position = Position::create(['name' => 'Funding Officer', 'type' => 'Fungsional']);

    Employee::create([
        'user_id' => User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@example.test'])->id,
        'department_id' => $finance->id,
        'position_fungsional_id' => $position->id,
        'nis' => 'NIS-001',
        'nik' => '3201010101010001',
        'npwp' => '01.234.567.8-901.000',
        'status' => 'active',
    ]);
    Employee::create([
        'user_id' => User::factory()->create(['name' => 'Siti Aminah'])->id,
        'department_id' => $it->id,
        'nis' => 'NIS-002',
        'status' => 'resign',
    ]);
});

test('requires the service token', function () {
    $this->getJson('/api/employees')->assertUnauthorized();
    $this->withToken('wrong')->getJson('/api/employees')->assertUnauthorized();
});

test('lists employees without sensitive fields', function () {
    $this->withToken('nkm_service-token')->getJson('/api/employees')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Budi Santoso')
        ->assertJsonPath('data.0.department', 'Departemen Finance')
        ->assertJsonPath('data.0.position_fungsional', 'Funding Officer')
        ->assertJsonMissingPath('data.0.nik')
        ->assertJsonMissingPath('data.0.npwp');
});

test('searches by name, email or NIS', function () {
    $this->withToken('nkm_service-token')->getJson('/api/employees?q=santoso')->assertJsonCount(1, 'data');
    $this->withToken('nkm_service-token')->getJson('/api/employees?q=BUDI@example')->assertJsonCount(1, 'data');
    $this->withToken('nkm_service-token')->getJson('/api/employees?q=NIS-002')->assertJsonPath('data.0.name', 'Siti Aminah');
    $this->withToken('nkm_service-token')->getJson('/api/employees?q=nobody')->assertJsonCount(0, 'data');
});

test('filters by department, position and status', function () {
    $this->withToken('nkm_service-token')->getJson('/api/employees?department=finance')->assertJsonCount(1, 'data');
    $this->withToken('nkm_service-token')->getJson('/api/employees?position=funding')->assertJsonPath('data.0.name', 'Budi Santoso');
    $this->withToken('nkm_service-token')->getJson('/api/employees?status=resign')->assertJsonPath('data.0.name', 'Siti Aminah');
    $this->withToken('nkm_service-token')->getJson('/api/employees?status=bogus')->assertUnprocessable();
});

test('paginates and sorts', function () {
    $this->withToken('nkm_service-token')->getJson('/api/employees?per_page=1&direction=desc')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Siti Aminah')
        ->assertJsonPath('meta.total', 2);

    $this->withToken('nkm_service-token')->getJson('/api/employees?per_page=101')->assertUnprocessable();
});

test('shows one employee', function () {
    $id = Employee::where('nis', 'NIS-001')->value('id');

    $this->withToken('nkm_service-token')->getJson("/api/employees/{$id}")
        ->assertOk()
        ->assertJsonPath('data.nis', 'NIS-001')
        ->assertJsonMissingPath('data.nik');

    $this->withToken('nkm_service-token')->getJson('/api/employees/999999')->assertNotFound();
});
