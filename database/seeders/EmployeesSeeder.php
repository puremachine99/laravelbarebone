<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class EmployeesSeeder extends Seeder
{
    public function run(): void
    {
        $departmentNames = [
            'BoD/BoA/C-Level',
            'Corporate Secretariat',
            'Finance',
            'People Culture and Strategy',
            'Product and RDI',
            'Revenue and Growth',
            'Technology',
        ];

        foreach ($departmentNames as $name) {
            DB::table('departments')->insertOrIgnore([
                'name' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $positionData = [
            ['Chief Executive Officer', 'Struktural'],
            ['Chief Business Officer', 'Struktural'],
            ['Chief Operating Officer', 'Struktural'],
            ['Direksi', 'Struktural'],
            ['Komisaris Utama', 'Struktural'],
            ['Komisaris Bidang Riset dan Kerjasama', 'Struktural'],
            ['Komisaris Bidang IT, HC, QA. Keuangan dan Umum', 'Struktural'],
            ['Dewan Penasihat', 'Struktural'],
            ['Corporate Secretary', 'Struktural'],
            ['Plt. Manager of Corporate Legal', 'Struktural'],
            ['Manager of Disbursement', 'Struktural'],
            ['Manager of Funding', 'Struktural'],
            ['SPV Iman, Imun, dan Aman', 'Struktural'],
            ['Head of Finance', 'Struktural'],
            ['Plt. Manager of Finance', 'Struktural'],
            ['Plt. Manager of Asset and General Affairs', 'Struktural'],
            ['Manager of Corporate Strategy and Performance Management and Plt. Manager of People and Culture', 'Struktural'],
            ['Head of People, Culture & Strategy', 'Struktural'],
            ['Manager of Executive Education', 'Struktural'],
            ['Manager of Online Course', 'Struktural'],
            ['Manager of Saas and AI', 'Struktural'],
            ['Manager of Consulting Service', 'Struktural'],
            ['Head of Product and RDI', 'Struktural'],
            ['Manager of Global Expansion', 'Struktural'],
            ['Vice President of Revenue and Growth', 'Struktural'],
            ['Manager of Digital Revenue and Marketing', 'Struktural'],
            ['Coordinator of Digital Creative', 'Struktural'],
            ['Manager of Technology', 'Struktural'],

            ['Product Consultant Ahli', 'Fungsional'],
            ['Product Consultant Madya', 'Fungsional'],
            ['Product Consultant Muda', 'Fungsional'],
            ['Product Consultant Pratama', 'Fungsional'],
            ['CEO Assistant Junior', 'Fungsional'],
            ['CEO Driver', 'Fungsional'],
            ['Legal Counsel Staff Junior', 'Fungsional'],
            ['Public Relation Senior', 'Fungsional'],
            ['Technical Secretary Officer Senior', 'Fungsional'],
            ['Technical Secretary Officer Junior', 'Fungsional'],
            ['Office Boy', 'Fungsional'],
            ['Project Administration Officer Pratama', 'Fungsional'],
            ['Project Administration Officer Madya', 'Fungsional'],
            ['Accounting Specialist Junior', 'Fungsional'],
            ['Tax Specialist Junior', 'Fungsional'],
            ['Financial Analyst Junior', 'Fungsional'],
            ['Funding Officer Senior', 'Fungsional'],
            ['Asset and General Affairs Officer Junior', 'Fungsional'],
            ['Administrative Officer Junior', 'Fungsional'],
            ['Payment Specialist Junior', 'Fungsional'],
            ['Security', 'Fungsional'],
            ['Chef', 'Fungsional'],
            ['People and Culture Officer Senior', 'Fungsional'],
            ['People and Culture Officer Junior', 'Fungsional'],
            ['Corporate Strategy and Performance Management Analyst Junior', 'Fungsional'],
            ['Consultant Relation Officer Junior', 'Fungsional'],
            ['Consultant Relation Officer Senior', 'Fungsional'],
            ['Online Course Officer Junior', 'Fungsional'],
            ['SaaS and AI Product Research Junior', 'Fungsional'],
            ['SaaS and AI Product Research Senior', 'Fungsional'],
            ['QA Specialist and Product Research Muda', 'Fungsional'],
            ['Graphic Designer Senior', 'Fungsional'],
            ['Graphic Designer Junior', 'Fungsional'],
            ['Production Specialist Junior', 'Fungsional'],
            ['Production Specialist Senior', 'Fungsional'],
            ['Executive Education Officer Junior', 'Fungsional'],
            ['Global Expert Specialist Junior', 'Fungsional'],
            ['Department Secretary', 'Fungsional'],
            ['Partnership Management Officer Pratama', 'Fungsional'],
            ['Partnership Management Officer Ahli', 'Fungsional'],
            ['Event and Program Specialist Senior', 'Fungsional'],
            ['Content Creator Senior', 'Fungsional'],
            ['Copywriter Senior', 'Fungsional'],
            ['Copywriter Junior', 'Fungsional'],
            ['Videographer and Editor Senior', 'Fungsional'],
            ['Digital Revenue and Marketing Officer Junior', 'Fungsional'],
            ['Performance Marketing Specialist Senior', 'Fungsional'],
            ['AI Engineer Senior', 'Fungsional'],
            ['AI Engineer Junior', 'Fungsional'],
            ['Programmer Senior', 'Fungsional'],
            ['Programmer Junior', 'Fungsional'],
            ['Development and Operations Engineer Senior', 'Fungsional'],
            ['Project Manager Junior', 'Fungsional'],
            ['System Analyst Senior', 'Fungsional'],
            ['Quality Assurance Specialist Junior', 'Fungsional'],
            ['User Interface/Experience Designer Junior', 'Fungsional'],
            ['Advisor Pengembangan', 'Fungsional'],
        ];

        foreach ($positionData as [$name, $type]) {
            DB::table('positions')->insertOrIgnore([
                'name' => $name,
                'type' => $type,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $deptId = fn (string $name) => DB::table('departments')->where('name', $name)->value('id');
        $posId = fn (string $name) => DB::table('positions')->where('name', $name)->value('id');

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
            $dt = \DateTime::createFromFormat('d/m/Y', trim($d));

            return $dt ? $dt->format('Y-m-d') : null;
        };

        $parseMarital = function (string $raw): string {
            return match (true) {
                str_contains($raw, 'Belum') => 'Lajang',
                default => 'Menikah',
            };
        };

        $filePath = database_path('seeders/jsonlawas/employees.json');
        if (! File::exists($filePath)) {
            $this->command->warn('File employees.json tidak ditemukan!');

            return;
        }

        $employees = json_decode(File::get($filePath), true);

        foreach ($employees as $emp) {
            $name = $emp['name'];
            $dept = $emp['department'];
            $jabStruktural = $emp['position_struktural'];
            $jabFungsional = $emp['position_fungsional'];
            $joinDate = $emp['join_date'];
            $statusRaw = $emp['status_raw'];
            $contractStart = $emp['contract_start'];
            $contractEnd = $emp['contract_end'];
            $nis = $emp['nis'];
            $nik = $emp['nik'];
            $npwp = $emp['npwp'];
            $edu = $emp['education_level'];
            $birthDate = $emp['birth_date'];
            $gender = $emp['gender'];
            $phone = $emp['phone'];
            $email = $emp['email'];
            $marital = $emp['marital'];

            $statusRawString = $statusRaw ?? '';
            [$empStatus, $isCltp] = $parseStatus($statusRawString);

            $cleanEmail = strtolower(trim(explode('/', $email ?? '')[0]));
            if (! $cleanEmail) {
                $cleanEmail = Str::slug($name).'@placeholder.invalid';
            }

            if (DB::table('users')->where('email', $cleanEmail)->exists()) {
                continue;
            }

            $cleanNik = $nik ?: null;
            $cleanNpwp = $npwp ?: null;

            if ($cleanNik && DB::table('employees')->where('nik', $cleanNik)->exists()) {
                $cleanNik = null;
            }
            if ($cleanNpwp && DB::table('employees')->where('npwp', $cleanNpwp)->exists()) {
                $cleanNpwp = null;
            }

            $userId = DB::table('users')->insertGetId([
                'name' => $name,
                'email' => $cleanEmail,
                'password' => bcrypt('qQ123!@#'),
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('employees')->insert([
                'user_id' => $userId,
                'department_id' => $dept ? $deptId($dept) : null,
                'position_struktural_id' => $jabStruktural ? $posId($jabStruktural) : null,
                'position_fungsional_id' => $jabFungsional ? $posId($jabFungsional) : null,
                'employment_status' => $empStatus,
                'is_cltp' => $isCltp,
                'contract_start_date' => $parseDate($contractStart),
                'contract_end_date' => $parseDate($contractEnd),
                'nis' => $nis ?: null,
                'nik' => $cleanNik,
                'npwp' => $cleanNpwp,
                'education_level' => $edu ?: null,
                'birth_date' => $parseDate($birthDate),
                'gender' => $gender ?: null,
                'phone_number' => $phone ?: null,
                'marital_status' => $parseMarital($marital ?? ''),
                'target_okr' => null,
                'pipeline_permissions' => null,
                'kpi_achieved_m1' => 0.00,
                'kpi_achieved_m2' => 0.00,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
