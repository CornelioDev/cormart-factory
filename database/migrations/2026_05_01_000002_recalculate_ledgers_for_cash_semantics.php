<?php

use App\Models\CapitalAccount;
use App\Models\Financing;
use App\Models\FundAccount;
use App\Models\FundMember;
use App\Models\Transaction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill de balances tras el refactor "claims-on-cash":
 * CapitalAccount + FundAccount = cash bancario real. Las distribuciones del
 * cierre y la reserva de impuesto pre-debitada del fondo dejan de descontarse;
 * los retiros a miembros y capitalizaciones sí descuentan.
 *
 * Sobreescribe los balances almacenados con el cálculo desde eventos brutos.
 * Idempotente: correrla varias veces produce el mismo resultado.
 */
return new class extends Migration
{
    public function up(): void
    {
        $activeCapital = (float) FundMember::where('active', true)
            ->where(fn ($q) => $q->where('type', 'capital')
                ->orWhere(fn ($q2) => $q2->where('type', 'in_kind')->where('contribution', '>', 0))
            )
            ->sum('contribution');

        $financingsActive = Financing::whereNotIn('status', ['solicited', 'cancelled']);
        $disbursedPhysical  = (float) (clone $financingsActive)->sum('disbursed_amount');
        $commissionRetained = (float) (clone $financingsActive)->sum('commission');
        $collectedToCapital = (float) (clone $financingsActive)->sum('collected_amount');
        $lateFeeCollected   = (float) Financing::sum('late_fee_amount');

        $confirmed = fn (string $type) => (float) Transaction::where('type', $type)
            ->where('status', 'confirmed')->sum('amount');

        $expenseTxn         = $confirmed('expense');
        $memberDisbursement = $confirmed('member_disbursement');
        $earningsToCapital  = $confirmed('earnings_to_capital');

        $newCapital = round(
            $activeCapital - $disbursedPhysical - $commissionRetained + $collectedToCapital,
            2
        );

        $newFund = round(
            $commissionRetained + $lateFeeCollected
            - $expenseTxn - $memberDisbursement - $earningsToCapital,
            2
        );

        DB::transaction(function () use ($newCapital, $newFund) {
            CapitalAccount::instance()->update(['balance' => $newCapital]);
            FundAccount::instance()->update(['balance' => $newFund]);
        });
    }

    public function down(): void
    {
        // No reversible: no se preserva el estado anterior. El refactor del
        // modelo de ledgers no soporta rollback automático.
    }
};
