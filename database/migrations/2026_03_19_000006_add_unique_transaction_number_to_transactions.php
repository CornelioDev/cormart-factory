<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fix existing duplicates: auto-tax expenses that copied the parent's transaction_number.
        // Replace them with the parent's TX code (extracted from notes).
        $duplicates = DB::table('transactions')
            ->select('transaction_number')
            ->whereNotNull('transaction_number')
            ->groupBy('transaction_number')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('transaction_number');

        foreach ($duplicates as $txNumber) {
            // The auto-generated tax expense has type=expense and notes starting with "Impuesto"
            $taxExpenses = DB::table('transactions')
                ->where('transaction_number', $txNumber)
                ->where('type', 'expense')
                ->where('notes', 'like', 'Impuesto por transacción%')
                ->get();

            foreach ($taxExpenses as $expense) {
                // Extract TX code from notes: "... por desembolso TX000XXX"
                if (preg_match('/TX\d+/', $expense->notes, $matches)) {
                    DB::table('transactions')
                        ->where('id', $expense->id)
                        ->update(['transaction_number' => $matches[0]]);
                }
            }
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->unique('transaction_number');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique(['transaction_number']);
        });
    }
};
