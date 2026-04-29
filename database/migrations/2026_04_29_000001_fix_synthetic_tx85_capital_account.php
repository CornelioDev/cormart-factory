<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrige el descuadre histórico de CapitalAccount causado por la transacción
 * sintética TX 85 — un disbursement registrado sobre FN000036 que estaba
 * partially_disbursed para sortear la restricción que bloqueaba cobros sobre
 * ese estado. La restricción se removió en el mismo release; esta migración
 * deshace el efecto contable de la TX sintética.
 *
 * Idempotente: si TX 85 no existe con esos valores exactos (ej. en BD de
 * desarrollo o si ya se aplicó), no hace nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $tx = DB::table('transactions')->where('id', 85)->first();

            if (! $tx
                || $tx->type !== 'disbursement'
                || abs((float) $tx->amount - 123205.58) > 0.01
            ) {
                return;
            }

            DB::table('financings')->where('code', 'FN000036')->update([
                'disbursed_amount' => 178444.31,
                'status'           => 'partially_disbursed',
                'due_date'         => null,
            ]);

            DB::table('transaction_financings')->where('transaction_id', 85)->delete();
            DB::table('transactions')->where('id', 85)->delete();

            DB::table('capital_account')->where('id', 1)->increment('balance', 123205.58);
        });
    }

    public function down(): void
    {
        // No-op: revertir requeriría re-crear la transacción sintética, lo cual
        // sería contradictorio con el propósito de esta migración. Si se necesita
        // rollback, hacerlo manualmente con justificación auditable.
    }
};
