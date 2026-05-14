<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@zenretreats.local'],
            [
                'name' => 'Super Admin',
                'phone' => '+923000000000',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => 'active',
            ]
        );
        $superAdmin->assignRole(RoleName::SuperAdmin->value);

        $financeAdmin = User::firstOrCreate(
            ['email' => 'finance@zenretreats.local'],
            [
                'name' => 'Finance Admin',
                'phone' => '+923000000001',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => 'active',
            ]
        );
        $financeAdmin->assignRole(RoleName::FinanceAdmin->value);

        $sales = User::firstOrCreate(
            ['email' => 'sales@zenretreats.local'],
            [
                'name' => 'Sales User',
                'phone' => '+923000000002',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => 'active',
            ]
        );
        $sales->assignRole(RoleName::Sales->value);
    }
}
