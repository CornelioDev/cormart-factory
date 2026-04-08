<?php

namespace Tests;

use App\Models\Parameter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

abstract class ServiceTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function seedParameters(): void
    {
        Parameter::insertOrIgnore([
            ['key' => 'commission_pct',    'value' => 5.0,   'description' => 'Comisión por factura (%)'],
            ['key' => 'fixed_return_pct',  'value' => 3.0,   'description' => 'Rendimiento fijo mensual (%)'],
            ['key' => 'reserve_pct',       'value' => 20.0,  'description' => 'Reserva sobre ganancia neta (%)'],
            ['key' => 'in_kind_pct',       'value' => 50.0,  'description' => 'Porcentaje post-reserva para naturaleza (%)'],
            ['key' => 'default_term_days', 'value' => 15.0,  'description' => 'Plazo estándar en días'],
            ['key' => 'tax_pct',           'value' => 0.15,    'description' => 'Impuesto sobre desembolsos (%)'],
            ['key' => 'late_fee_pct',      'value' => 5.0,    'description' => 'Mora por atraso cada 30 días (%)'],
            ['key' => 'due_alert_days',    'value' => 5,                        'description' => 'Días de anticipación para alerta de vencimiento'],
            ['key' => 'alert_send_time',   'value' => '07:00',                  'description' => 'Hora de envío de alertas diarias (HH:MM)'],
            ['key' => 'timezone',          'value' => 'America/Santo_Domingo',  'description' => 'Zona horaria del sistema'],
        ]);
    }

    protected function createRole(string $name): Role
    {
        return Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
}
