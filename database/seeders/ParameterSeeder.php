<?php

namespace Database\Seeders;

use App\Models\Parameter;
use Illuminate\Database\Seeder;

class ParameterSeeder extends Seeder
{
    public function run(): void
    {
        $parameters = [
            [
                'key'         => 'commission_pct',
                'value'       => 5.0000,
                'description' => 'Comisión por factura (%)',
            ],
            [
                'key'         => 'fixed_return_pct',
                'value'       => 3.0000,
                'description' => 'Rendimiento fijo mensual para aportantes de capital (%)',
            ],
            [
                'key'         => 'reserve_pct',
                'value'       => 20.0000,
                'description' => 'Reserva del fondo sobre ganancia neta (%)',
            ],
            [
                'key'         => 'in_kind_pct',
                'value'       => 50.0000,
                'description' => 'Porcentaje del post-reserva para el aportante en naturaleza (%)',
            ],
            [
                'key'         => 'default_term_days',
                'value'       => 15.0000,
                'description' => 'Plazo estándar de facturas en días',
            ],
            [
                'key'         => 'tax_pct',
                'value'       => 0.15,
                'description' => 'Impuesto sobre desembolsos (%)',
            ],
        ];

        foreach ($parameters as $parameter) {
            Parameter::create($parameter);
        }
    }
}