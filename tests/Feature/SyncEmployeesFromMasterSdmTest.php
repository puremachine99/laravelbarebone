<?php

use App\Enums\EmployeeStatus;
use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;

function writeSdmCsv(string $path, array $header, array $rows): void
{
    $file = fopen($path, 'w');
    fputcsv($file, $header);
    foreach ($rows as $row) {
        fputcsv($file, $row);
    }
    fclose($file);
}

it('replaces the login email with the personal email, moves the old one to username, and preserves existing position when CSV only has a single Jabatan column', function () {
    $department = Department::create(['name' => 'Departemen Finance']);
    $position = Position::create(['name' => 'Manager of Finance', 'type' => 'Struktural']);

    $user = User::factory()->create(['name' => 'Samsul', 'email' => 'samsul@smartid.co.id']);
    $employee = Employee::create([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'position_struktural_id' => $position->id,
        'status' => EmployeeStatus::Active,
        'nis' => '19750815202003417',
    ]);

    $csvPath = storage_path('framework/testing/sdm_new_format.csv');
    writeSdmCsv($csvPath, [
        'NIS', 'ID Fingerprint', 'Nama Lengkap', 'Departemen', 'Jabatan', 'Grade', '4-Box',
        'Kategori Jabatan', 'Tanggal Bergabung SmartID', 'Lama Kerja (Tahun)', 'Status Kepegawaian',
        'Tanggal Awal Kontrak', 'Tanggal Terakhir Kontrak', 'Squad', 'NIK', 'NPWP', 'Pendidikan',
        'Tanggal Lahir', 'Jenis Kelamin', 'No.Telephone', 'Email',
    ], [
        [
            '19750815202003417', '148', 'Samsul', 'Departemen Finance', 'Office Boy', 'G1', '',
            'Penunjang', '1 Maret 2020', '6,6', 'PKWT', '1 Januari 2026', '1 Januari 2027', '',
            '3507041508750004', '', '', '15 Agustus 1975', 'Laki-laki', '+62 812-3109-4170',
            'samsul.personal@gmail.com',
        ],
    ]);

    $this->artisan('app:sync-employees-master-sdm', ['--file' => $csvPath])->assertSuccessful();

    $employee->refresh();
    $user->refresh();

    expect($user->email)->toBe('samsul.personal@gmail.com')
        ->and($user->username)->toBe('samsul@smartid.co.id')
        ->and($user->secondary_email)->toBeNull()
        ->and($employee->position_struktural_id)->toBe($position->id)
        ->and($employee->employment_status)->toBe('PKWT')
        ->and($employee->status)->toBe(EmployeeStatus::Active);

    @unlink($csvPath);
});

it('matches employees by NIS even when the CSV name differs slightly and does not replace an email already taken by another user', function () {
    $otherUser = User::factory()->create(['email' => 'taken@gmail.com']);

    $user = User::factory()->create(['name' => 'Fariza Maher Fauzy', 'email' => 'fariza@smartid.co.id']);
    $employee = Employee::create([
        'user_id' => $user->id,
        'status' => EmployeeStatus::Active,
        'nis' => '19970515202003037',
    ]);

    $csvPath = storage_path('framework/testing/sdm_nis_match.csv');
    writeSdmCsv($csvPath, [
        'NIS', 'ID Fingerprint', 'Nama Lengkap', 'Departemen', 'Jabatan', 'Grade', '4-Box',
        'Kategori Jabatan', 'Tanggal Bergabung SmartID', 'Lama Kerja (Tahun)', 'Status Kepegawaian',
        'Tanggal Awal Kontrak', 'Tanggal Terakhir Kontrak', 'Squad', 'NIK', 'NPWP', 'Pendidikan',
        'Tanggal Lahir', 'Jenis Kelamin', 'No.Telephone', 'Email',
    ], [
        [
            '19970515202003037', '1', 'Fariza Maher Fauzy, S.AP.', '', 'Graphic Designer', '', '',
            '', '', '', '', '', '', '', '', '', '', '', '', '', 'taken@gmail.com',
        ],
    ]);

    $this->artisan('app:sync-employees-master-sdm', ['--file' => $csvPath])->assertSuccessful();

    $user->refresh();

    expect($user->email)->toBe('fariza@smartid.co.id')
        ->and($user->username)->toBeNull();

    @unlink($csvPath);
});

it('creates new employees with their personal email from the CSV as the login email', function () {
    $csvPath = storage_path('framework/testing/sdm_new_employee.csv');
    writeSdmCsv($csvPath, [
        'NIS', 'ID Fingerprint', 'Nama Lengkap', 'Departemen', 'Jabatan', 'Grade', '4-Box',
        'Kategori Jabatan', 'Tanggal Bergabung SmartID', 'Lama Kerja (Tahun)', 'Status Kepegawaian',
        'Tanggal Awal Kontrak', 'Tanggal Terakhir Kontrak', 'Squad', 'NIK', 'NPWP', 'Pendidikan',
        'Tanggal Lahir', 'Jenis Kelamin', 'No.Telephone', 'Email',
    ], [
        [
            '19990101202501001', '99', 'Karyawan Baru', '', 'Staff', '', '', '', '', '', '', '', '',
            '', '', '', '', '', '', '', 'karyawan.baru@gmail.com',
        ],
    ]);

    $this->artisan('app:sync-employees-master-sdm', ['--file' => $csvPath])->assertSuccessful();

    $user = User::where('name', 'Karyawan Baru')->first();

    expect($user)->not->toBeNull()
        ->and($user->email)->toBe('karyawan.baru@gmail.com')
        ->and($user->secondary_email)->toBeNull();

    @unlink($csvPath);
});

it('inactivates employees not present in the new CSV', function () {
    $stayingUser = User::factory()->create(['name' => 'Wiwik Ernawati']);
    Employee::create(['user_id' => $stayingUser->id, 'status' => EmployeeStatus::Active, 'nis' => '19840502202003416']);

    $missingUser = User::factory()->create(['name' => 'Zzz Gone Employee']);
    $missingEmployee = Employee::create(['user_id' => $missingUser->id, 'status' => EmployeeStatus::Active, 'nis' => 'not-in-csv']);

    $csvPath = storage_path('framework/testing/sdm_inactivate.csv');
    writeSdmCsv($csvPath, [
        'NIS', 'ID Fingerprint', 'Nama Lengkap', 'Departemen', 'Jabatan', 'Grade', '4-Box',
        'Kategori Jabatan', 'Tanggal Bergabung SmartID', 'Lama Kerja (Tahun)', 'Status Kepegawaian',
        'Tanggal Awal Kontrak', 'Tanggal Terakhir Kontrak', 'Squad', 'NIK', 'NPWP', 'Pendidikan',
        'Tanggal Lahir', 'Jenis Kelamin', 'No.Telephone', 'Email',
    ], [
        [
            '19840502202003416', '2', 'Wiwik Ernawati', '', 'Chef', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',
        ],
    ]);

    $this->artisan('app:sync-employees-master-sdm', ['--file' => $csvPath])->assertSuccessful();

    $missingEmployee->refresh();
    $missingUser->refresh();

    expect($missingEmployee->status)->toBe(EmployeeStatus::Inactive)
        ->and($missingUser->status)->toBe(UserStatus::Suspended);

    @unlink($csvPath);
});
