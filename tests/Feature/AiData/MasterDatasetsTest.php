<?php

use App\Enums\EmployeeStatus;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Opd;
use App\Models\Output;
use App\Models\Position;
use App\Models\ProductCategory;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Region;
use App\Models\ServiceCenter;
use App\Models\Urusan;
use App\Models\User;
use App\Services\AiData\DataQueryService;
use App\Services\AiData\DatasetCatalog;
use App\Support\ResourcePermissions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function masterDataUser(array $datasets, bool $allDepartments = true, ?Department $department = null): User
{
    ResourcePermissions::ensureExist();

    $permissions = ['manage-ai-center', ...array_map(fn (string $dataset) => "ai-data-{$dataset}.view", $datasets)];

    if ($allDepartments) {
        $permissions[] = 'ai-data-all-departments.view';
    }

    $user = User::factory()->create();
    $user->givePermissionTo(array_map(fn (string $name) => Permission::findOrCreate($name), $permissions));

    if ($department) {
        Employee::create(['user_id' => $user->id, 'department_id' => $department->id]);
    }

    return $user;
}

/**
 * The employee's department lives on the employee record; users.department_id is deliberately left empty to prove
 * the datasets do not depend on that synced copy.
 */
function staff(string $name, Department $department, array $attributes = []): Employee
{
    return Employee::create([
        'user_id' => User::factory()->create(['name' => $name, 'department_id' => null])->id,
        'department_id' => $department->id,
        ...$attributes,
    ]);
}

function master(string $dataset, array $params = [], ?User $user = null): array
{
    return app(DataQueryService::class)->run($user ?? masterDataUser([str_replace('_', '-', $dataset)]), ['dataset' => $dataset, ...$params]);
}

beforeEach(function () {
    $this->tech = Department::create(['name' => 'Departemen Digital Transformation', 'code' => 'DT']);
    $this->finance = Department::create(['name' => 'Departemen Finance', 'code' => 'FIN']);
});

test('employees lists active employees with only basic work data', function () {
    $lead = Position::create(['name' => 'Head of Engineering', 'type' => 'Struktural']);
    staff('Andi', $this->tech, [
        'nis' => 'NIS-001', 'position_struktural_id' => $lead->id,
        'nik' => '3171234567890001', 'npwp' => '01.234.567.8-901.000', 'marital_status' => 'Menikah', 'employment_status' => 'PKWT',
    ]);
    staff('Bela', $this->tech, ['nis' => 'NIS-002', 'status' => EmployeeStatus::Resign]);
    staff('Cici', $this->tech, ['nis' => 'NIS-003'])->delete();
    staff('Dodi', $this->finance, ['nis' => 'NIS-004']);

    $result = master('employees', [
        'group_by' => ['employee', 'nis', 'position_struktural'],
        'filters' => ['department' => 'digital transformation'],
        'metrics' => ['employees'],
    ]);

    expect($result['period'])->toBeNull()
        ->and($result['rows'])->toBe([['employee' => 'Andi', 'nis' => 'NIS-001', 'position_struktural' => 'Head of Engineering', 'employees' => 1]]);

    $json = json_encode($result);

    foreach (['3171234567890001', '01.234.567.8-901.000', 'Menikah', 'PKWT', 'Bela', 'Cici', 'resign'] as $hidden) {
        expect($json)->not->toContain($hidden);
    }
});

test('employees counts per department and matches any position type', function () {
    $analyst = Position::create(['name' => 'Data Analyst', 'type' => 'Fungsional']);
    staff('Andi', $this->tech, ['position_fungsional_id' => $analyst->id]);
    staff('Budi', $this->tech);
    staff('Dodi', $this->finance);

    expect(master('employees', ['group_by' => ['department']])['rows'])->toBe([
        ['department' => 'Departemen Digital Transformation', 'employees' => 2],
        ['department' => 'Departemen Finance', 'employees' => 1],
    ]);

    expect(master('employees', ['group_by' => ['employee'], 'filters' => ['position' => 'analyst']])['rows'])
        ->toBe([['employee' => 'Andi', 'employees' => 1]]);
});

test('employees is limited to the user own department, taken from their employee record', function () {
    staff('Andi', $this->tech);
    staff('Dodi', $this->finance);
    $financeUser = masterDataUser(['employees'], allDepartments: false, department: $this->finance);

    $result = master('employees', ['group_by' => ['employee']], $financeUser);

    // The requesting user is a Finance employee too, so they appear in their own department's list.
    expect($result['scope'])->toBe('own_department')
        ->and(collect($result['rows'])->pluck('employee')->all())->toContain('Dodi', $financeUser->name)->not->toContain('Andi');
});

test('departments lists every department with its active employees', function () {
    staff('Andi', $this->tech);
    staff('Bela', $this->tech, ['status' => EmployeeStatus::Resign]);

    $result = master('departments', ['group_by' => ['department', 'code']]);

    expect($result['scope'])->toBe('global')
        ->and($result['rows'])->toBe([
            ['department' => 'Departemen Digital Transformation', 'code' => 'DT', 'employees' => 1],
            ['department' => 'Departemen Finance', 'code' => 'FIN', 'employees' => 0],
        ]);
});

test('roles counts users per role and department without naming them', function () {
    $officer = Role::findOrCreate('Funding Officer');
    staff('Rahasia Satu', $this->finance)->user->assignRole($officer);
    staff('Rahasia Dua', $this->finance)->user->assignRole($officer);
    Role::findOrCreate('Product Admin');

    $result = master('roles', ['group_by' => ['role', 'department'], 'filters' => ['role' => 'funding']]);

    expect($result['rows'])->toBe([['role' => 'Funding Officer', 'department' => 'Departemen Finance', 'users' => 2]])
        ->and(json_encode($result))->not->toContain('Rahasia');
});

test('regions, OPD and urusan are linked through regencies', function () {
    $region = Region::factory()->create(['name' => 'Region 3']);
    $jatim = Province::factory()->create(['name' => 'Jawa Timur']);
    $malang = Regency::factory()->create(['name' => 'Kabupaten Malang', 'province_id' => $jatim->id, 'region_id' => $region->id]);
    $surabaya = Regency::factory()->create(['name' => 'Kota Surabaya', 'province_id' => $jatim->id, 'region_id' => $region->id]);
    $education = Urusan::create(['code' => '1.01', 'urusan' => 'Pendidikan']);
    $health = Urusan::create(['code' => '1.02', 'urusan' => 'Kesehatan']);
    Opd::factory()->create(['name' => 'Dinas Pendidikan Malang', 'regency_id' => $malang->id, 'jenis' => 'OPD'])->urusans()->attach([$education->id, $health->id]);
    Opd::factory()->create(['name' => 'Dinas Kesehatan Surabaya', 'regency_id' => $surabaya->id, 'jenis' => 'OPD'])->urusans()->attach($health->id);
    Opd::factory()->create(['name' => 'RSUD Surabaya', 'regency_id' => $surabaya->id, 'jenis' => 'Unit Kesehatan']);

    expect(master('regions', ['group_by' => ['region']])['rows'])
        ->toBe([['region' => 'Region 3', 'provinces' => 1, 'regencies' => 2, 'opds' => 3]]);

    expect(master('opds', ['group_by' => ['jenis']])['rows'])
        ->toBe([['jenis' => 'OPD', 'opds' => 2], ['jenis' => 'Unit Kesehatan', 'opds' => 1]]);

    expect(master('opds', ['group_by' => ['opd', 'regency'], 'filters' => ['urusan' => 'kesehatan', 'province' => 'jawa timur']])['rows'])
        ->toBe([
            ['opd' => 'Dinas Kesehatan Surabaya', 'regency' => 'Kota Surabaya', 'opds' => 1],
            ['opd' => 'Dinas Pendidikan Malang', 'regency' => 'Kabupaten Malang', 'opds' => 1],
        ]);

    expect(master('urusans', ['group_by' => ['urusan', 'code']])['rows'])
        ->toBe([['urusan' => 'Kesehatan', 'code' => '1.02', 'opds' => 2], ['urusan' => 'Pendidikan', 'code' => '1.01', 'opds' => 1]]);
});

test('products lists the product master with category and status', function () {
    $division = Division::create(['code' => '1', 'name' => 'Tata Kelola']);
    $center = ServiceCenter::create(['division_id' => $division->id, 'code' => '2', 'full_code' => '1.2', 'name' => 'Layanan SDM']);
    ProductCategory::create(['code' => 'A', 'name' => 'Pendampingan']);
    Output::create(['division_id' => $division->id, 'service_center_id' => $center->id, 'category_code' => 'A', 'output_name' => 'Analisis SDM', 'pelaksana' => 'Tim SDM', 'status' => true]);
    Output::create(['division_id' => $division->id, 'service_center_id' => $center->id, 'category_code' => 'A', 'output_name' => 'Audit Lama', 'pelaksana' => 'Tim SDM', 'status' => false]);

    expect(master('products', ['group_by' => ['category', 'status']])['rows'])->toBe([
        ['category' => 'Pendampingan', 'status' => 'Aktif', 'products' => 1],
        ['category' => 'Pendampingan', 'status' => 'Nonaktif', 'products' => 1],
    ]);

    expect(master('products', ['group_by' => ['product', 'code', 'division', 'service_center'], 'filters' => ['name' => 'analisis']])['rows'])
        ->toBe([['product' => 'Analisis SDM', 'code' => '1.2.A.1', 'division' => 'Tata Kelola', 'service_center' => 'Layanan SDM', 'products' => 1]]);
});

test('master data rejects periods and comparisons', function (array $params) {
    expect(fn () => master('departments', $params))->toThrow(ValidationException::class);
})->with([
    'month' => [['from' => '2026-09']],
    'date' => [['from_date' => '2026-09-01']],
    'all time' => [['all_time' => true]],
    'comparison' => [['compare_previous' => true]],
]);

test('master data rejects filters and dimensions outside the catalog', function (array $params) {
    expect(fn () => master('employees', $params))->toThrow(ValidationException::class);
})->with([
    'nik dimension' => [['group_by' => ['nik']]],
    'status dimension' => [['group_by' => ['status']]],
    'npwp filter' => [['filters' => ['npwp' => '01']]],
]);

test('the catalog tells the AI which datasets are not time based', function () {
    $catalog = collect(app(DatasetCatalog::class)->describeFor(masterDataUser(['employees', 'fo-achievement'])))->keyBy('dataset');

    expect($catalog['employees']['time_based'])->toBeFalse()
        ->and($catalog['employees']['skill'])->toBe('master_data')
        ->and($catalog['fo_achievement']['time_based'])->toBeTrue();
});

test('each master dataset needs its own permission', function () {
    $user = masterDataUser(['departments']);

    expect(fn () => master('employees', [], $user))->toThrow(AuthorizationException::class);
    expect(master('departments', [], $user)['rows'])->toHaveCount(1);
});
