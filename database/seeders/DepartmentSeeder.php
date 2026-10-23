<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Starter departments with a short badge code (max 3 letters) and accent color. `color` is a
     * semantic key consumed by resources/views/components/department-code.blade.php.
     *
     * @var array<string, array{code: string, color: string}>
     */
    protected array $departments = [
        'Board of Directors' => ['code' => 'BOD', 'color' => 'amber'],
        'Finance' => ['code' => 'FIN', 'color' => 'rose'],
        'People & Culture' => ['code' => 'PC', 'color' => 'fuchsia'],
        'Technology' => ['code' => 'TEC', 'color' => 'teal'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->departments as $name => $attributes) {
            Department::updateOrCreate(['name' => $name], $attributes);
        }
    }
}
