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
            [
                'key'         => 'late_fee_pct',
                'value'       => 5.0000,
                'description' => 'Mora por atraso sobre saldo pendiente por cada 30 días (%)',
            ],
            [
                'key'         => 'due_alert_days',
                'value'       => 5,
                'description' => 'Días de anticipación para alerta de vencimiento',
            ],
            [
                'key'         => 'alert_send_time',
                'value'       => '07:00',
                'description' => 'Hora de envío de alertas diarias (HH:MM)',
            ],
            [
                'key'         => 'timezone',
                'value'       => 'America/Santo_Domingo',
                'description' => 'Zona horaria del sistema',
            ],
        ];

        foreach ($parameters as $parameter) {
            Parameter::firstOrCreate(
                ['key' => $parameter['key']],
                $parameter,
            );
        }
    }
}