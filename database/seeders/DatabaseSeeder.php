<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ParameterSeeder::class,
            FundMemberSeeder::class,
            ShieldSeeder::class,
            CompanySeeder::class,
            ClientSeeder::class,
            TestUsersSeeder::class,
            RolePermissionsSeeder::class,
            FinancingAndTransactionSeeder::class,
        ]);
    }
}
