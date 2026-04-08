<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('parameters')->insertOrIgnore([
            [
                'key'         => 'timezone',
                'value'       => 'America/Santo_Domingo',
                'description' => 'Zona horaria del sistema',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('parameters')->where('key', 'timezone')->delete();
    }
};
