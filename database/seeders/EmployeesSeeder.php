<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class EmployeesSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = DB::table('users')->where('email', 'supernakama@smartid.co.id')->first();
        $superAdminId = $superAdmin ? $superAdmin->id : null;

        // Clear existing data in correct order to avoid foreign key errors
        if ($superAdminId) {
            DB::table('employees')->where('user_id', '!=', $superAdminId)->delete();
            DB::table('users')->where('id', '!=', $superAdminId)->delete();
        } else {
            DB::table('employees')->delete();
            DB::table('users')->delete();
        }
        DB::table('departments')->delete();
        DB::table('positions')->delete();

        // 1. Load employees.json for extra data merging (NIK, NPWP, Usia/Birthdate, Gender, Marital, Phone, Education)
        $jsonPath = database_path('seeders/jsonlawas/employees.json');
        $jsonEmployees = [];

        $normalizeName = function (string $str) {
            $baseName = explode(',', $str)[0];

            return strtolower(preg_replace('/[^a-zA-Z]/', '', $baseName));
        };

        if (File::exists($jsonPath)) {
            $jsonList = json_decode(File::get($jsonPath), true);
            if (is_array($jsonList)) {
                foreach ($jsonList as $jEmp) {
                    if (isset($jEmp['name'])) {
                        $key = $normalizeName($jEmp['name']);
                        $jsonEmployees[$key] = $jEmp;
                    }
                }
            }
        }

        // 2. Read sdm.csv as the main data source
        $csvPath = database_path('seeders/jsonlawas/sdm.csv');
        if (! File::exists($csvPath)) {
            $this->command->warn('File sdm.csv tidak ditemukan!');

            return;
        }

        $file = fopen($csvPath, 'r');
        $header = fgetcsv($file);

        $rows = [];
        $departments = [];
        $positions = []; // name => type

        while (($row = fgetcsv($file)) !== false) {
            if (empty($row) || ! isset($row[2]) || trim($row[2]) === '') {
                continue;
            }
            $rows[] = $row;

            $deptName = trim($row[3] ?? '');
            if ($deptName !== '') {
                $departments[$deptName] = true;
            }

            $struktural = trim($row[4] ?? '');
            if ($struktural !== '') {
                $cleanStruktural = str_replace(["\r", "\n"], ' ', $struktural);
                $positions[$cleanStruktural] = 'Struktural';
            }

            $fungsional = trim($row[5] ?? '');
            if ($fungsional !== '') {
                $cleanFungsional = str_replace(["\r", "\n"], ' ', $fungsional);
                $positions[$cleanFungsional] = 'Fungsional';
            }
        }
        fclose($file);

        $departmentsMap = [];
        $positionsMap = [];

        // Insert Departments
        foreach (array_keys($departments) as $deptName) {
            $deptId = DB::table('departments')->insertGetId([
                'name' => $deptName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $departmentsMap[$deptName] = $deptId;
        }

        // Insert Positions
        foreach ($positions as $posName => $posType) {
            $posId = DB::table('positions')->insertGetId([
                'name' => $posName,
                'type' => $posType,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $positionsMap[$posName] = $posId;
        }

        // Helper functions
        $parseStatus = function (string $raw): array {
            $parts = array_map('trim', explode(',', $raw));
            $status = $parts[0] ?? null;
            $isCltp = in_array('CLTP', $parts, true);
            $validStatuses = ['PKWT', 'PKWTT', 'Internship', 'Mitra Strategis', 'Bond Agreement'];
            if (! in_array($status, $validStatuses, true)) {
                $status = null;
            }

            return [$status, $isCltp];
        };

        $parseDate = function (?string $d): ?string {
            if (! $d) {
                return null;
            }
            $d = trim($d);
            if ($d === '' || $d === '-' || strtolower($d) === 'null') {
                return null;
            }

            $monthsIndo = [
                'januari' => 'January',
                'februari' => 'February',
                'maret' => 'March',
                'april' => 'April',
                'mei' => 'May',
                'juni' => 'June',
                'juli' => 'July',
                'agustus' => 'August',
                'september' => 'September',
                'oktober' => 'October',
                'november' => 'November',
                'desember' => 'December',
            ];

            $lowerDate = strtolower($d);
            foreach ($monthsIndo as $indo => $eng) {
                if (str_contains($lowerDate, $indo)) {
                    $d = str_ireplace($indo, $eng, $d);
                    break;
                }
            }

            try {
                $dt = new \DateTime($d);

                return $dt->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        };

        $parseMarital = function (string $raw): string {
            return match (true) {
                str_contains($raw, 'Belum') => 'Lajang',
                default => 'Menikah',
            };
        };

        // Seed Employees & Users
        foreach ($rows as $row) {
            $name = trim($row[2]);
            $deptName = trim($row[3] ?? '');
            $struktural = str_replace(["\r", "\n"], ' ', trim($row[4] ?? ''));
            $fungsional = str_replace(["\r", "\n"], ' ', trim($row[5] ?? ''));

            $joinDate = trim($row[6] ?? '');
            $statusRaw = trim($row[9] ?? '');

            $contractStart = trim($row[10] ?? '');
            if ($contractStart === '') {
                $contractStart = $joinDate;
            }
            $contractEnd = trim($row[11] ?? '');
            $nis = trim($row[12] ?? '');

            [$empStatus, $isCltp] = $parseStatus($statusRaw);

            // Match with json for extra fields
            $normName = $normalizeName($name);
            $matchedJson = $jsonEmployees[$normName] ?? null;

            $nik = $matchedJson['nik'] ?? null;
            $npwp = $matchedJson['npwp'] ?? null;
            $education = $matchedJson['education_level'] ?? null;
            $birthDate = isset($matchedJson['birth_date']) ? $parseDate($matchedJson['birth_date']) : null;
            $gender = $matchedJson['gender'] ?? null;
            $phone = $matchedJson['phone'] ?? null;
            $maritalStatus = isset($matchedJson['marital']) ? $parseMarital($matchedJson['marital']) : 'Lajang';

            // Generate clean email from name
            $emailName = Str::slug(explode(',', $name)[0], '.');
            $email = $emailName.'@smartid.co.id';

            // Avoid duplicate email address
            $emailCounter = 1;
            while (DB::table('users')->where('email', $email)->exists()) {
                $email = $emailName.$emailCounter.'@smartid.co.id';
                $emailCounter++;
            }

            // Create user
            $userId = DB::table('users')->insertGetId([
                'name' => $name,
                'email' => $email,
                'password' => bcrypt('qQ123!@#'),
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create employee
            DB::table('employees')->insert([
                'user_id' => $userId,
                'department_id' => ($deptName !== '' && isset($departmentsMap[$deptName])) ? $departmentsMap[$deptName] : null,
                'position_struktural_id' => ($struktural !== '' && isset($positionsMap[$struktural])) ? $positionsMap[$struktural] : null,
                'position_fungsional_id' => ($fungsional !== '' && isset($positionsMap[$fungsional])) ? $positionsMap[$fungsional] : null,
                'employment_status' => $empStatus,
                'is_cltp' => $isCltp,
                'contract_start_date' => $parseDate($contractStart),
                'contract_end_date' => $parseDate($contractEnd),
                'nis' => $nis ?: null,
                'nik' => $nik,
                'npwp' => $npwp,
                'education_level' => $education ?: null,
                'birth_date' => $birthDate,
                'gender' => $gender ?: null,
                'phone_number' => $phone ?: null,
                'marital_status' => $maritalStatus,
                'target_okr' => null,
                'pipeline_permissions' => null,
                'kpi_achieved_m1' => 0.00,
                'kpi_achieved_m2' => 0.00,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Assign role & permissions for "Revenue and Growth" department employees
            $isRevenueAndGrowth = (
                strtolower($deptName) === 'departemen revenue and growth' ||
                strtolower($deptName) === 'revenue and growth'
            );

            if ($isRevenueAndGrowth) {
                $userModel = User::find($userId);
                if ($userModel) {
                    $userModel->assignRole('Partnership Admin');
                    $userModel->givePermissionTo([
                        'manage-partnership',
                        'manage-pipeline',
                        'manage-products',
                        'manage-investors',
                    ]);
                }
            }
        }

        // Seed specific regional PMO employees
        $pmoKaryawans = [
            [
                'name' => 'Andre Dwi Prasaja, S.I.P., M.Sc.',
                'email' => 'andre@smartid.co.id',
                'region_name' => 'Region 1',
            ],
            [
                'name' => 'Chausar Yahya Ramadhanto, S.E.',
                'email' => 'chausar@smartid.co.id',
                'region_name' => 'Region 2',
            ],
            [
                'name' => 'Arif Susianto, A.Md.Kom.',
                'email' => 'arif@smartid.co.id',
                'region_name' => 'Region 3',
            ],
            [
                'name' => 'Ariq Dhamas Salsabil, S.I.P.',
                'email' => 'ariq@smartid.co.id',
                'region_name' => 'Region 4',
            ],
            [
                'name' => 'Toriqul Mujahidi Ashshobrul Karim, S.AP.',
                'email' => 'toriq@smartid.co.id',
                'region_name' => 'Region 5',
            ],
        ];

        $dept = DB::table('departments')->where('name', 'Departemen Revenue and Growth')->first();
        $deptId = $dept ? $dept->id : null;

        foreach ($pmoKaryawans as $pmo) {
            $region = DB::table('regions')->where('name', $pmo['region_name'])->first();
            $regionId = $region ? $region->id : null;

            // Find existing user by name
            $userModel = User::where('name', $pmo['name'])->first();

            if ($userModel) {
                $userId = $userModel->id;
                // Update existing employee region
                DB::table('employees')->where('user_id', $userId)->update([
                    'region_id' => $regionId,
                    'updated_at' => now(),
                ]);

                $employee = DB::table('employees')->where('user_id', $userId)->first();
                $employeeId = $employee ? $employee->id : null;
            } else {
                // If not found, create new user and employee
                $userId = DB::table('users')->insertGetId([
                    'name' => $pmo['name'],
                    'email' => $pmo['email'],
                    'password' => bcrypt('qQ123!@#'),
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $employeeId = DB::table('employees')->insertGetId([
                    'user_id' => $userId,
                    'department_id' => $deptId,
                    'region_id' => $regionId,
                    'employment_status' => 'PKWTT',
                    'is_cltp' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $userModel = User::find($userId);
            }

            // Assign region to employee_regions pivot table
            if ($employeeId && $regionId) {
                DB::table('employee_regions')->insertOrIgnore([
                    'employee_id' => $employeeId,
                    'region_id' => $regionId,
                ]);
            }

            // Assign Spatie Role 'PMO' and permission 'verify-manager'
            if ($userModel) {
                $userModel->assignRole('PMO');
                $userModel->givePermissionTo(['manage-pipeline', 'verify-manager']);
            }
        }
    }
}
