<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class ShieldSeeder extends Seeder
{
    public function run(): void
    {
        // Generar permisos de Filament Shield para todos los recursos
        $this->command->call('shield:generate', ['--all' => true, '--panel' => 'admin', '--no-interaction' => true]);

        Role::firstOrCreate(
            ['name' => 'super_admin', 'guard_name' => 'web']
        );

        Role::firstOrCreate(
            ['name' => 'operator', 'guard_name' => 'web']
        );

        Role::firstOrCreate(
            ['name' => 'member', 'guard_name' => 'web']
        );

        Role::firstOrCreate(
            ['name' => 'company_user', 'guard_name' => 'web']
        );

        $user = User::firstOrCreate(
            ['email' => 'cornelio@proton.me'],
            ['name' => 'José Cornelio', 'password' => bcrypt('admin123')]
        );
        $user->assignRole('super_admin');
    }
}