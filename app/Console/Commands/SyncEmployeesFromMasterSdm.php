<?php

namespace App\Console\Commands;

use App\Enums\EmployeeStatus;
use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

#[Signature('app:sync-employees-master-sdm {--file= : Custom path to the Master SDM CSV} {--dry-run : Roll back all changes after reporting counts}')]
#[Description('Update employees, departments and positions from the Master SDM CSV without truncating existing data')]
class SyncEmployeesFromMasterSdm extends Command
{
    /**
     * Departments that were renamed in the new Master SDM export.
     *
     * @var array<string, string>
     */
    private const DEPARTMENT_RENAMES = [
        'Departemen People Culture and Strategy' => 'Departemen People and Culture',
        'Departemen Technology' => 'Departemen Digital Transformation',
    ];

    /**
     * Positions that were renamed (same role, wording/typo changed) in the new export.
     *
     * @var array<string, string>
     */
    private const POSITION_RENAMES = [
        'Videographer dan Editor Junior' => 'Videographer and Editor Junior',
        'Plt. Manager of Finance' => 'Manager of Finance',
        'People and Culture Officer Senior' => 'People Operation and Culture Officer Senior',
        'Corporate Strategy and Performance Management Analyst Junior' => 'Performance Management Analyst Junior',
        'Manager of Technology' => 'Manager of Digital Transformation',
    ];

    /**
     * Employees known to be seed/testing data, excluded from the "not found in CSV" inactivation.
     *
     * @var array<int, string>
     */
    private const EXCLUDED_FROM_INACTIVATION = [
        'nopel',
        'Ms. Alvena Quigley',
        'Mr. Fidel Emmerich PhD',
        'Kristopher Mills',
        'Shannon Schoen',
    ];

    public function handle(): void
    {
        $csvPath = $this->option('file') ?: database_path('seeders/jsonlawas/Master SDM (Umum) - Master.csv');

        if (! File::exists($csvPath)) {
            $this->error("File tidak ditemukan: {$csvPath}");

            return;
        }

        $normalizeName = fn (string $str) => strtolower(preg_replace('/[^a-zA-Z]/', '', explode(',', $str)[0]));

        DB::beginTransaction();

        try {
            $this->syncFromCsv($csvPath, $normalizeName);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        if ($this->option('dry-run')) {
            DB::rollBack();
            $this->warn('Dry run: semua perubahan di atas di-ROLLBACK, tidak disimpan ke database.');

            return;
        }

        DB::commit();
    }

    private function syncFromCsv(string $csvPath, \Closure $normalizeName): void
    {
        $this->renameDepartments();
        $this->renamePositions();

        $rows = $this->readCsv($csvPath);

        $departmentsMap = Department::pluck('id', 'name')->all();
        $positionsMap = Position::pluck('id', 'name')->all();

        $positionsMap = $this->createMissingPositions($rows, $positionsMap);
        $departmentsMap = $this->createMissingDepartments($rows, $departmentsMap);

        $normDbUsers = [];
        foreach (User::all() as $user) {
            $normDbUsers[$normalizeName($user->name)] = $user;
        }

        $updated = 0;
        $created = 0;
        $matchedKeys = [];

        foreach ($rows as $row) {
            $name = trim($row[2]);
            $key = $normalizeName($name);
            $matchedKeys[$key] = true;

            $attributes = $this->buildEmployeeAttributes($row, $departmentsMap, $positionsMap);

            $user = $normDbUsers[$key] ?? null;

            if ($user) {
                $employee = Employee::firstOrNew(['user_id' => $user->id]);
                $employee->fill($attributes);
                $employee->status = EmployeeStatus::Active;
                $employee->save();
                $updated++;

                continue;
            }

            $emailName = Str::slug(explode(',', $name)[0], '.');
            $email = "{$emailName}@smartid.co.id";
            $counter = 1;
            while (User::where('email', $email)->exists()) {
                $email = "{$emailName}{$counter}@smartid.co.id";
                $counter++;
            }

            $newUser = User::create([
                'name' => $name,
                'email' => $email,
                'password' => bcrypt('smartid2026'),
                'email_verified_at' => now(),
            ]);

            $employee = new Employee($attributes);
            $employee->user_id = $newUser->id;
            $employee->status = EmployeeStatus::Active;
            $employee->save();

            $normDbUsers[$key] = $newUser;
            $created++;
        }

        $inactivated = $this->inactivateMissingEmployees($matchedKeys, $normalizeName);

        $this->info("Employee diupdate: {$updated}");
        $this->info("Employee baru dibuat: {$created}");
        $this->info("Employee ditandai nonaktif: {$inactivated}");
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readCsv(string $csvPath): array
    {
        $file = fopen($csvPath, 'r');
        fgetcsv($file);

        $rows = [];
        while (($row = fgetcsv($file)) !== false) {
            if (empty($row) || ! isset($row[2]) || trim($row[2]) === '') {
                continue;
            }
            $rows[] = $row;
        }
        fclose($file);

        return $rows;
    }

    private function renameDepartments(): void
    {
        foreach (self::DEPARTMENT_RENAMES as $old => $new) {
            Department::where('name', $old)->update(['name' => $new]);
        }
    }

    private function renamePositions(): void
    {
        foreach (self::POSITION_RENAMES as $old => $new) {
            Position::where('name', $old)->update(['name' => $new]);
        }
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     * @param  array<string, int>  $positionsMap
     * @return array<string, int>
     */
    private function createMissingPositions(array $rows, array $positionsMap): array
    {
        $columnsToType = [4 => 'Struktural', 5 => 'Fungsional', 6 => 'Teknis'];

        foreach ($rows as $row) {
            foreach ($columnsToType as $column => $type) {
                $value = trim(str_replace(["\r", "\n"], ' ', $row[$column] ?? ''));
                if ($value === '' || isset($positionsMap[$value])) {
                    continue;
                }

                $positionsMap[$value] = Position::create([
                    'name' => $value,
                    'type' => $type,
                ])->id;
            }
        }

        return $positionsMap;
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     * @param  array<string, int>  $departmentsMap
     * @return array<string, int>
     */
    private function createMissingDepartments(array $rows, array $departmentsMap): array
    {
        foreach ($rows as $row) {
            $name = trim($row[3] ?? '');
            if ($name === '' || isset($departmentsMap[$name])) {
                continue;
            }

            $departmentsMap[$name] = Department::create(['name' => $name])->id;
        }

        return $departmentsMap;
    }

    /**
     * @param  array<int, string>  $row
     * @param  array<string, int>  $departmentsMap
     * @param  array<string, int>  $positionsMap
     * @return array<string, mixed>
     */
    private function buildEmployeeAttributes(array $row, array $departmentsMap, array $positionsMap): array
    {
        $deptName = trim($row[3] ?? '');
        $struktural = trim(str_replace(["\r", "\n"], ' ', $row[4] ?? ''));
        $fungsional = trim(str_replace(["\r", "\n"], ' ', $row[5] ?? ''));
        $teknis = trim(str_replace(["\r", "\n"], ' ', $row[6] ?? ''));

        $joinDate = trim($row[8] ?? '');
        $statusRaw = trim($row[11] ?? '');
        $contractStart = trim($row[12] ?? '') ?: $joinDate;
        $contractEnd = trim($row[13] ?? '');

        [$employmentStatus, $isCltp] = $this->parseStatus($statusRaw);

        return [
            'department_id' => $deptName !== '' ? ($departmentsMap[$deptName] ?? null) : null,
            'position_struktural_id' => $struktural !== '' ? ($positionsMap[$struktural] ?? null) : null,
            'position_fungsional_id' => $fungsional !== '' ? ($positionsMap[$fungsional] ?? null) : null,
            'position_teknis_id' => $teknis !== '' ? ($positionsMap[$teknis] ?? null) : null,
            'employment_status' => $employmentStatus,
            'is_cltp' => $isCltp,
            'contract_start_date' => $this->parseDate($contractStart),
            'contract_end_date' => $this->parseDate($contractEnd),
            'nis' => trim($row[14] ?? '') ?: null,
            'nik' => trim($row[15] ?? '') ?: null,
            'npwp' => trim($row[16] ?? '') ?: null,
            'education_level' => trim($row[17] ?? '') ?: null,
            'birth_date' => $this->parseDate($row[18] ?? null),
            'gender' => trim($row[19] ?? '') ?: null,
            'phone_number' => $this->firstPhoneNumber($row[20] ?? ''),
        ];
    }

    /**
     * @return array{0: ?string, 1: bool}
     */
    private function parseStatus(string $raw): array
    {
        $parts = array_map('trim', explode(',', $raw));
        $status = $parts[0] ?? null;
        $isCltp = in_array('CLTP', $parts, true);
        $validStatuses = ['PKWT', 'PKWTT', 'Internship', 'Mitra Strategis', 'Bond Agreement'];
        if (! in_array($status, $validStatuses, true)) {
            $status = null;
        }

        return [$status, $isCltp];
    }

    private function firstPhoneNumber(string $raw): ?string
    {
        $first = trim(explode('/', $raw)[0]);

        return $first !== '' ? substr($first, 0, 30) : null;
    }

    private function parseDate(?string $d): ?string
    {
        if (! $d) {
            return null;
        }
        $d = trim($d);
        if ($d === '' || $d === '-' || strtolower($d) === 'null') {
            return null;
        }

        $monthsIndo = [
            'januari' => 'January', 'februari' => 'February', 'maret' => 'March',
            'april' => 'April', 'mei' => 'May', 'juni' => 'June',
            'juli' => 'July', 'agustus' => 'August', 'september' => 'September',
            'oktober' => 'October', 'november' => 'November', 'desember' => 'December',
        ];

        $lowerDate = strtolower($d);
        foreach ($monthsIndo as $indo => $eng) {
            if (str_contains($lowerDate, $indo)) {
                $d = str_ireplace($indo, $eng, $d);
                break;
            }
        }

        try {
            return (new \DateTime($d))->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @param  array<string, bool>  $matchedKeys
     */
    private function inactivateMissingEmployees(array $matchedKeys, \Closure $normalizeName): int
    {
        $superAdminId = DB::table('users')->where('email', 'supernakama@smartid.co.id')->value('id');
        $excludedKeys = array_map($normalizeName, self::EXCLUDED_FROM_INACTIVATION);

        $count = 0;

        Employee::with('user')->get()->each(function (Employee $employee) use (&$count, $matchedKeys, $superAdminId, $excludedKeys, $normalizeName) {
            if (! $employee->user || $employee->user->id === $superAdminId) {
                return;
            }

            $key = $normalizeName($employee->user->name);

            if (isset($matchedKeys[$key]) || in_array($key, $excludedKeys, true)) {
                return;
            }

            if ($employee->status === EmployeeStatus::Inactive) {
                return;
            }

            $employee->status = EmployeeStatus::Inactive;
            $employee->status_date = now();
            $employee->status_reason = 'Tidak ditemukan pada update data Master SDM tanggal '.now()->format('Y-m-d');
            $employee->save();

            $employee->user->update(['status' => UserStatus::Suspended]);

            $count++;
        });

        return $count;
    }
}
