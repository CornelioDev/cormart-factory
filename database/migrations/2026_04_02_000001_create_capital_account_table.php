<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capital_account', function (Blueprint $table) {
            $table->id();
            $table->decimal('balance', 15, 2)->default(0);
            $table->timestamps();
        });

        // Calcular balance inicial: capital aportado - capital comprometido en calle
        $contribution = (float) DB::table('fund_members')
            ->where('type', 'capital')
            ->where('active', true)
            ->sum('contribution');

        $inStreet = (float) DB::table('financings')
            ->whereIn('status', ['disbursed', 'partially_collected'])
            ->selectRaw('COALESCE(SUM(amount - collected_amount), 0) as total')
            ->value('total');

        DB::table('capital_account')->insert([
            'balance'    => round($contribution - $inStreet, 2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('capital_account');
    }
};
