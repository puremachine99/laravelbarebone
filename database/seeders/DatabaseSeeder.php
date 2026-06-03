<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
            ['user_id' => $superAdmin->id],
            [
                'id' => Str::uuid(),
            ]
        );

        $this->call([
            RegionProvinceRegencySeeder::class,
            EmployeesSeeder::class,
        ]);
    }
}
