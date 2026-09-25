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

    /**
     * Whether the CSV being processed has the legacy 3-way position columns
     * (Jabatan Struktural / Fungsional / Teknis). When false (e.g. the CSV
     * only has a single "Jabatan" column), position fields are left untouched.
     */
    private bool $hasPositionColumns = false;

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

        $this->hasPositionColumns = ! empty($rows) && array_key_exists('Jabatan Struktural', $rows[0]);

        if (! $this->hasPositionColumns) {
            $this->warn('Kolom Jabatan Struktural/Fungsional/Teknis tidak ditemukan di CSV ini — data jabatan yang sudah ada TIDAK akan diubah.');
        }

        $departmentsMap = Department::pluck('id', 'name')->all();
        $positionsMap = Position::pluck('id', 'name')->all();

        if ($this->hasPositionColumns) {
            $positionsMap = $this->createMissingPositions($rows, $positionsMap);
        }
        $departmentsMap = $this->createMissingDepartments($rows, $departmentsMap);

        $normDbUsers = [];
        foreach (User::all() as $user) {
            $normDbUsers[$normalizeName($user->name)] = $user;
        }

        $employeesByNis = Employee::whereNotNull('nis')->where('nis', '!=', '')->get()->keyBy('nis');

        $updated = 0;
        $created = 0;
        $emailsReplaced = 0;
        $matchedEmployeeIds = [];

        foreach ($rows as $row) {
            $name = trim($row['Nama Lengkap'] ?? '');
            if ($name === '') {
                continue;
            }

            $key = $normalizeName($name);
            $nis = trim($row['NIS'] ?? '');
            $personalEmail = $this->firstEmail($row['Email'] ?? '');

            $attributes = $this->buildEmployeeAttributes($row, $departmentsMap, $positionsMap);

            $employee = ($nis !== '' && isset($employeesByNis[$nis])) ? $employeesByNis[$nis] : null;
            $user = $employee?->user ?? ($normDbUsers[$key] ?? null);

            if ($user) {
                $employee = $employee ?? Employee::firstOrNew(['user_id' => $user->id]);
                $employee->fill($attributes);
                $employee->status = EmployeeStatus::Active;
                $employee->save();
                $updated++;

                if ($this->replacePrimaryEmail($user, $personalEmail)) {
                    $emailsReplaced++;
                }

                $matchedEmployeeIds[$employee->id] = true;

                continue;
            }

            $email = $personalEmail;
            if ($email === '' || User::where('email', $email)->exists()) {
                $emailName = Str::slug(explode(',', $name)[0], '.');
                $email = "{$emailName}@smartid.co.id";
                $counter = 1;
                while (User::where('email', $email)->exists()) {
                    $email = "{$emailName}{$counter}@smartid.co.id";
                    $counter++;
                }
            }

            $newUser = User::create([
                'name' => $name,
                'email' => $email,
                'secondary_email' => null,
                'password' => bcrypt('smartid2026'),
                'email_verified_at' => now(),
            ]);

            $employee = new Employee($attributes);
            $employee->user_id = $newUser->id;
            $employee->status = EmployeeStatus::Active;
            $employee->save();

            $normDbUsers[$key] = $newUser;
            $matchedEmployeeIds[$employee->id] = true;
            $created++;
        }

        $inactivated = $this->inactivateMissingEmployees($matchedEmployeeIds, $normalizeName);

        $this->info("Employee diupdate: {$updated}");
        $this->info("Employee baru dibuat: {$created}");
        $this->info("Email login diganti ke email pribadi: {$emailsReplaced}");
        $this->info("Employee ditandai nonaktif: {$inactivated}");
    }

    /**
     * Replaces the user's login email with their personal email from the CSV.
     * The previous login email is preserved as `username` (when not already set)
     * so existing credentials keep working. `secondary_email` is always cleared.
     */
    private function replacePrimaryEmail(User $user, string $personalEmail): bool
    {
        $changed = false;

        if ($personalEmail !== '' && $user->email !== $personalEmail) {
            $emailTaken = User::where('email', $personalEmail)->where('id', '!=', $user->id)->exists();

            if ($emailTaken) {
                $this->warn("Email '{$personalEmail}' sudah dipakai user lain, email login tidak diganti untuk: {$user->name}");
            } else {
                if ($user->username === null) {
                    $usernameTaken = User::where('id', '!=', $user->id)
                        ->where(fn ($q) => $q->where('username', $user->email)->orWhere('email', $user->email))
                        ->exists();

                    if (! $usernameTaken) {
                        $user->username = $user->email;
                    }
                }

                $user->email = $personalEmail;
                $changed = true;
            }
        }

        if ($user->secondary_email !== null) {
            $user->secondary_email = null;
            $changed = true;
        }

        if ($changed) {
            $user->save();
        }

        return $changed;
    }

    private function firstEmail(string $raw): string
    {
        return trim(explode('/', $raw)[0]);
    }

    /**
     * Reads the CSV into header-keyed associative rows. Handles a leading blank
     * row before the real header, which some exports include.
     *
     * @return array<int, array<string, string>>
     */
    private function readCsv(string $csvPath): array
    {
        $file = fopen($csvPath, 'r');

        $header = null;
        $rows = [];

        while (($line = fgetcsv($file)) !== false) {
            if (empty(array_filter($line, fn ($v) => trim((string) $v) !== ''))) {
                continue;
            }

            if ($header === null) {
                if (! in_array('Nama Lengkap', array_map('trim', $line), true)) {
                    continue;
                }
                $header = array_map('trim', $line);

                continue;
            }

            $assoc = [];
            foreach ($header as $i => $columnName) {
                if ($columnName === '') {
                    continue;
                }
                $assoc[$columnName] = $line[$i] ?? '';
            }

            if (! isset($assoc['Nama Lengkap']) || trim($assoc['Nama Lengkap']) === '') {
                continue;
            }

            $rows[] = $assoc;
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
     * @param  array<int, array<string, string>>  $rows
     * @param  array<string, int>  $positionsMap
     * @return array<string, int>
     */
    private function createMissingPositions(array $rows, array $positionsMap): array
    {
        $columnsToType = [
            'Jabatan Struktural' => 'Struktural',
            'Jabatan Fungsional' => 'Fungsional',
            'Jabatan Teknis' => 'Teknis',
        ];

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
     * @param  array<int, array<string, string>>  $rows
     * @param  array<string, int>  $departmentsMap
     * @return array<string, int>
     */
    private function createMissingDepartments(array $rows, array $departmentsMap): array
    {
        foreach ($rows as $row) {
            $name = trim($row['Departemen'] ?? '');
            if ($name === '' || isset($departmentsMap[$name])) {
                continue;
            }

            $departmentsMap[$name] = Department::create(['name' => $name])->id;
        }

        return $departmentsMap;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, int>  $departmentsMap
     * @param  array<string, int>  $positionsMap
     * @return array<string, mixed>
     */
    private function buildEmployeeAttributes(array $row, array $departmentsMap, array $positionsMap): array
    {
        $deptName = trim($row['Departemen'] ?? '');

        $joinDate = trim($row['Tanggal Bergabung SmartID'] ?? '');
        $statusRaw = trim($row['Status Kepegawaian'] ?? '');
        $contractStart = trim($row['Tanggal Awal Kontrak'] ?? '') ?: $joinDate;
        $contractEnd = trim($row['Tanggal Terakhir Kontrak'] ?? '');

        [$employmentStatus, $isCltp] = $this->parseStatus($statusRaw);

        $attributes = [
            'department_id' => $deptName !== '' ? ($departmentsMap[$deptName] ?? null) : null,
            'employment_status' => $employmentStatus,
            'is_cltp' => $isCltp,
            'contract_start_date' => $this->parseDate($contractStart),
            'contract_end_date' => $this->parseDate($contractEnd),
            'nis' => trim($row['NIS'] ?? '') ?: null,
            'nik' => trim($row['NIK'] ?? '') ?: null,
            'npwp' => trim($row['NPWP'] ?? '') ?: null,
            'education_level' => trim($row['Pendidikan'] ?? '') ?: null,
            'birth_date' => $this->parseDate($row['Tanggal Lahir'] ?? null),
            'gender' => trim($row['Jenis Kelamin'] ?? '') ?: null,
            'phone_number' => $this->firstPhoneNumber($row['No.Telephone'] ?? ''),
        ];

        if ($this->hasPositionColumns) {
            $struktural = trim(str_replace(["\r", "\n"], ' ', $row['Jabatan Struktural'] ?? ''));
            $fungsional = trim(str_replace(["\r", "\n"], ' ', $row['Jabatan Fungsional'] ?? ''));
            $teknis = trim(str_replace(["\r", "\n"], ' ', $row['Jabatan Teknis'] ?? ''));

            $attributes['position_struktural_id'] = $struktural !== '' ? ($positionsMap[$struktural] ?? null) : null;
            $attributes['position_fungsional_id'] = $fungsional !== '' ? ($positionsMap[$fungsional] ?? null) : null;
            $attributes['position_teknis_id'] = $teknis !== '' ? ($positionsMap[$teknis] ?? null) : null;
        }

        return $attributes;
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
     * @param  array<int, bool>  $matchedEmployeeIds
     */
    private function inactivateMissingEmployees(array $matchedEmployeeIds, \Closure $normalizeName): int
    {
        $superAdminId = DB::table('users')->where('email', 'supernakama@smartid.co.id')->value('id');
        $excludedKeys = array_map($normalizeName, self::EXCLUDED_FROM_INACTIVATION);

        $count = 0;

        Employee::with('user')->get()->each(function (Employee $employee) use (&$count, $matchedEmployeeIds, $superAdminId, $excludedKeys, $normalizeName) {
            if (! $employee->user || $employee->user->id === $superAdminId) {
                return;
            }

            if (isset($matchedEmployeeIds[$employee->id])) {
                return;
            }

            $key = $normalizeName($employee->user->name);

            if (in_array($key, $excludedKeys, true)) {
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
