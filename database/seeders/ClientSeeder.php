<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Company;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        $cormart = Company::where('name', 'Cormart Soluciones SRL')->first();
        $ysetech = Company::where('name', 'Ysetech SRL')->first();

        $clients = [
            // Deudores de Cormart Soluciones
            ['company_id' => $cormart->id, 'name' => 'Innovatech Solutions SRL',       'active' => true],
            ['company_id' => $cormart->id, 'name' => 'Grupo Rodriguez & Asociados SA', 'active' => true],
            ['company_id' => $cormart->id, 'name' => 'Caribbean Trading Co. SRL',      'active' => true],

            // Deudores de Ysetech
            ['company_id' => $ysetech->id, 'name' => 'DataCenter DR SRL',              'active' => true],
            ['company_id' => $ysetech->id, 'name' => 'TechPro Distribuciones SA',      'active' => true],
            ['company_id' => $ysetech->id, 'name' => 'Sistemas Integrados del Caribe', 'active' => true],
        ];

        foreach ($clients as $data) {
            Client::firstOrCreate(
                ['company_id' => $data['company_id'], 'name' => $data['name']],
                $data
            );
        }
    }
}
