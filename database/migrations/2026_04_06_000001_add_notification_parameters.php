<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('parameters')->insertOrIgnore([
            [
                'key'         => 'due_alert_days',
                'value'       => 5,
                'description' => 'Días de anticipación para alerta de vencimiento',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'alert_send_time',
                'value'       => '07:00',
                'description' => 'Hora de envío de alertas diarias (HH:MM)',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('parameters')->whereIn('key', ['due_alert_days', 'alert_send_time'])->delete();
    }
};
