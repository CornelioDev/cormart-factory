<?php

use App\Models\CapitalAccount;
use App\Models\Financing;
use App\Models\FundMember;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrige el descuadre de CapitalAccount causado por el bug en
 * TransactionService::applyCollectionToFinancings — el cálculo de $totalRemaining
 * dentro del loop de mutación sobre-asignaba pago al último financiamiento en
 * cobros multi-financiamiento, sobre-acreditando capital.
 *
 * El bug se arrastraba históricamente, pero la migración del 2026-05-01
 * (recalculate_ledgers_for_cash_semantics) reseteó el balance al valor calculado
 * por la fórmula. Después, la única transacción de cobro multi-financiamiento
 * fue la TX 99 (2026-05-05), que volvió a inyectar el sobre-crédito por
 * 11,198.20 (monto de FN000038, contado dos veces).
 *
 * Idempotente: solo aplica si el descuadre actual coincide con el esperado
 * (-11,198.20). Si la BD ya está balanceada, no hace nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        $totalCapital = (float) FundMember::where('active', true)
            ->where(fn ($q) => $q->where('type', 'capital')
                ->orWhere(fn ($q2) => $q2->where('type', 'in_kind')->where('contribution', '>', 0))
            )
            ->sum('contribution');

        $financings = Financing::whereNotIn('status', ['solicited', 'cancelled']);
        $collected   = (float) (clone $financings)->sum('collected_amount');
        $disbursed   = (float) (clone $financings)->sum('disbursed_amount');
        $commission  = (float) (clone $financings)->sum('commission');

        $expected = round($totalCapital + $collected - $disbursed - $commission, 2);
        $actual   = (float) CapitalAccount::instance()->balance;
        $diff     = round($expected - $actual, 2);

        // Solo aplicar si el descuadre coincide con el sobre-crédito conocido
        // (capital actual está exactamente 11,198.20 por encima de lo esperado).
        if (abs($diff + 11198.20) > 0.01) {
            return;
        }

        DB::table('capital_account')->where('id', 1)->decrement('balance', 11198.20);
    }

    public function down(): void
    {
        // No-op: revertir restauraría el descuadre.
    }
};
