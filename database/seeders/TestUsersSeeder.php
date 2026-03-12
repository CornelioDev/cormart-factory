<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestUsersSeeder extends Seeder
{
    public function run(): void
    {
        // Operador interno
        $operator = User::firstOrCreate(
            ['email' => 'operador@test.com'],
            ['name' => 'Operador Test', 'password' => bcrypt('password')]
        );
        $operator->assignRole('operator');

        // Miembro del fondo
        $member = User::firstOrCreate(
            ['email' => 'miembro@test.com'],
            ['name' => 'Miembro Test', 'password' => bcrypt('password')]
        );
        $member->assignRole('member');

        // Usuario externo de Cormart Soluciones
        $cormart = Company::where('name', 'Cormart Soluciones SRL')->first();
        if ($cormart) {
            $u = User::firstOrCreate(
                ['email' => 'cormart@test.com'],
                ['name' => 'Usuario Cormart', 'company_id' => $cormart->id, 'password' => bcrypt('password')]
            );
            $u->update(['company_id' => $cormart->id]);
            $u->assignRole('company_user');
        }

        // Usuario externo de Ysetech
        $ysetech = Company::where('name', 'Ysetech SRL')->first();
        if ($ysetech) {
            $u = User::firstOrCreate(
                ['email' => 'ysetech@test.com'],
                ['name' => 'Usuario Ysetech', 'company_id' => $ysetech->id, 'password' => bcrypt('password')]
            );
            $u->update(['company_id' => $ysetech->id]);
            $u->assignRole('company_user');
        }
    }
}
