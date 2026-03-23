<?php

namespace Database\Seeders;

use App\Models\FundMember;
use Illuminate\Database\Seeder;

class FundMemberSeeder extends Seeder
{
    public function run(): void
    {
        // Total capital: 370,000
        // Familia Cornelio Pérez: 300,000 = 81.08%
        // Miembro Prueba:          70,000 = 18.92%

        FundMember::create([
            'name'            => 'Familia Cornelio Pérez',
            'type'            => 'capital',
            'contribution'    => 300000.00,
            'fund_percentage' => 81.0811,
            'active'          => true,
            'joined_at'       => '2025-01-01',
        ]);

        FundMember::create([
            'name'            => 'Miembro Prueba',
            'type'            => 'capital',
            'contribution'    => 70000.00,
            'fund_percentage' => 18.9189,
            'active'          => true,
            'joined_at'       => '2025-01-01',
        ]);

        FundMember::create([
            'name'            => 'Aportante Naturaleza',
            'type'            => 'in_kind',
            'contribution'    => 0,
            'fund_percentage' => 0,
            'active'          => true,
            'joined_at'       => '2025-01-01',
        ]);
    }
}
