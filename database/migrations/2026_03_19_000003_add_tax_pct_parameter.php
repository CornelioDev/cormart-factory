<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('parameters')->insertOrIgnore([
            'key'         => 'tax_pct',
            'value'       => 0.15,
            'description' => 'Impuesto sobre desembolsos (%)',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('parameters')->where('key', 'tax_pct')->delete();
    }
};
