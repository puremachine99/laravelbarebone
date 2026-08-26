<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {

        $superAdmin = User::updateOrCreate(
            ['email' => 'supernakama@smartid.co.id'],
            [
                'name' => 'Super Admin Nakama',
                'password' => Hash::make('qQ123123'),
                'email_verified_at' => now(),
            ]
        );

        Employee::updateOrCreate(
            ['user_id' => $superAdmin->id]
        );

        $this->call([
            RoleAndPermissionSeeder::class,
            RegionProvinceRegencySeeder::class,
            EmployeesSeeder::class,
            UrusanSeeder::class,
            OpdSeeder::class,
            ProductSeeder::class,
            ContactPositionSeeder::class,
            PipelineSeeder::class,
            ContactSeeder::class,
            PrediksiCardSeeder::class,
            RencanaPitchingCardSeeder::class,
            FundingOfficerSeeder::class,
            FoFunnelDataSeeder::class,
            DailyReportFollowUpSeeder::class,
        ]);
    }
}
