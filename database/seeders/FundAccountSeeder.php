<?php

namespace Database\Seeders;

use App\Models\Financing;
use App\Models\Transaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FundAccountSeeder extends Seeder
{
    public function run(): void
    {
        // Calcular balance histórico: comisiones acreditadas - gastos debitados
        $totalCommissions = Financing::whereIn('status', ['disbursed', 'partially_collected', 'collected'])
            ->sum('commission');

        $totalExpenses = Transaction::where('type', 'expense')
            ->where('status', 'confirmed')
            ->sum('amount');

        $balance = round((float) $totalCommissions - (float) $totalExpenses, 2);

        DB::table('fund_account')->updateOrInsert(
            ['id' => 1],
            [
                'balance' => $balance,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
}
