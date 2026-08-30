<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentCodeSeeder extends Seeder
{
    /**
     * Short badge code (max 3 letters) and accent color per department, keyed by the exact
     * department name seeded in EmployeesSeeder. `color` is a semantic key consumed by
     * resources/views/components/department-badge.blade.php.
     *
     * @var array<string, array{code: string, color: string}>
     */
    protected array $codes = [
        'BoD/BoA/C-Level' => ['code' => 'C', 'color' => 'amber'],
        'Corporate Secretariat' => ['code' => 'CS', 'color' => 'violet'],
        'Departemen Finance' => ['code' => 'FF', 'color' => 'rose'],
        'Departemen People Culture and Strategy' => ['code' => 'PCS', 'color' => 'fuchsia'],
        'Departemen Product and RDI' => ['code' => 'RDI', 'color' => 'cyan'],
        'Departemen Revenue and Growth' => ['code' => 'DRO', 'color' => 'orange'],
        'Departemen Technology' => ['code' => 'TEC', 'color' => 'teal'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->codes as $name => $attributes) {
            Department::where('name', $name)->update($attributes);
        }
    }
}
