<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financings', function (Blueprint $table) {
            $table->decimal('late_fee_amount', 15, 2)->default(0.00)->after('collected_amount');
            $table->decimal('late_fee_pending', 15, 2)->default(0.00)->after('late_fee_amount');
        });

        DB::table('parameters')->insertOrIgnore([
            'key'         => 'late_fee_pct',
            'value'       => '5.0',
            'description' => 'Porcentaje de mora por cada 30 días de atraso',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('financings', function (Blueprint $table) {
            $table->dropColumn(['late_fee_amount', 'late_fee_pending']);
        });

        DB::table('parameters')->where('key', 'late_fee_pct')->delete();
    }
};
