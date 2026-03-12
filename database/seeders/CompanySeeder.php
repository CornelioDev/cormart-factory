<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $companies = [
            [
                'name'          => 'Cormart Soluciones SRL',
                'rnc'           => '1-30-45678-9',
                'contact_name'  => 'José Cornelio',
                'contact_email' => 'jose@cormartsoluciones.do',
                'contact_phone' => '809-555-0111',
                'active'        => true,
            ],
            [
                'name'          => 'Ysetech SRL',
                'rnc'           => '1-30-98765-4',
                'contact_name'  => 'Yse Martínez',
                'contact_email' => 'yse@ysetech.do',
                'contact_phone' => '809-555-0222',
                'active'        => true,
            ],
        ];

        foreach ($companies as $data) {
            Company::firstOrCreate(['name' => $data['name']], $data);
        }
    }
}
