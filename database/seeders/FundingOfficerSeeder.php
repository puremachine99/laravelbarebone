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
     * user_id => is this person the Admin FO (structural head)?
     *
     * @var array<int, bool>
     */
    private const FUNDING_OFFICERS = [
        31 => false, // Chanira Nuansa Bunga, S.I.Kom., M.I.Kom.
        56 => false, // Oktavia Helda Theresia Wonsiwor, ST.
        33 => false, // Dwi Febriyano Jatmiko, S.Psi.
        57 => false, // Virgy Sharfina Dewinta, S.IP.
        47 => true,  // Putri Caniagy Lucky, S.E. — Admin FO
    ];

    /**
     * Run the database seeds.
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

        foreach (self::FUNDING_OFFICERS as $userId => $isAdminFo) {
            $employee = Employee::where('user_id', $userId)->first();

            if (! $employee) {
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
