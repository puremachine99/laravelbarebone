<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Position;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class FundingOfficerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Funding Officers and their structural head (Admin FO) are derived from the
     * imported HR data (department + original job title from sdm.csv) rather than
     * a hardcoded list of user IDs, so re-imports keep this in sync automatically.
     */
    public function run(): void
    {
        $adminFoPosition = Position::firstOrCreate(
            ['name' => 'Admin FO'],
            ['type' => 'Struktural'],
        );

        $foPosition = Position::firstOrCreate(
            ['name' => 'FO'],
            ['type' => 'Fungsional'],
        );

        // Spatie roles: both only unlock the Funding menu (access-investors), nothing else.
        $investorAdminRole = Role::findOrCreate('Investor Admin');
        $investorAdminRole->givePermissionTo(Permission::findOrCreate('access-investors'));

        $foRole = Role::findOrCreate('Funding Officer');
        $foRole->givePermissionTo(Permission::findOrCreate('access-investors'));

        $financeEmployees = Employee::whereHas('department', fn ($query) => $query->where('name', 'Departemen Finance'))
            ->with(['user', 'positionStruktural', 'positionFungsional'])
            ->get();

        foreach ($financeEmployees as $employee) {
            if (! $employee->user) {
                continue;
            }

            $isAdminFo = str_starts_with((string) $employee->positionStruktural?->name, 'Head of Funding');
            $isFo = str_starts_with((string) $employee->positionFungsional?->name, 'Funding Officer');

            if (! $isAdminFo && ! $isFo) {
                continue;
            }

            $employee->update([
                'position_struktural_id' => $isAdminFo ? $adminFoPosition->id : $employee->position_struktural_id,
                'position_fungsional_id' => $foPosition->id,
            ]);

            $employee->user->syncRoles([$isAdminFo ? $investorAdminRole : $foRole]);
        }
    }
}
