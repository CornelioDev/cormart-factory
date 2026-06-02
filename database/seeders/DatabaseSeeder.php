<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Seeders de producción (siempre)
        $this->call([
            ParameterSeeder::class,
            FundMemberSeeder::class,
            ShieldSeeder::class,
            CompanySeeder::class,
            ClientSeeder::class,
            RolePermissionsSeeder::class,
            NotificationSettingsSeeder::class,
        ]);

        // Seeders de desarrollo (nunca en producción)
        if (! app()->environment('production')) {
            $this->call([
                TestUsersSeeder::class,
                FinancingAndTransactionSeeder::class,
                FundAccountSeeder::class,
                MonthlyClosingSeeder::class,
            ]);
        }
    }
}
