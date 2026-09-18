<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Convierte `capital_account` a InnoDB.
 *
 * La tabla se creó en MyISAM (2026_04_02_000001_create_capital_account_table),
 * mientras que `fund_account` y `transactions` son InnoDB. MyISAM no participa
 * en transacciones ni soporta bloqueo de fila, y eso rompe dos garantías que el
 * resto del sistema da por sentadas:
 *
 * 1. Atomicidad. TransactionService::create() envuelve el desembolso en
 *    DB::transaction(), pero el débito al capital ocurre sobre MyISAM: si algo
 *    lanza después — validación de solvencia, error al vincular financiamientos —
 *    todo revierte MENOS el balance del capital, que queda permanentemente mal.
 *    Verificado en local: un rollback dejó `transactions` limpia y el balance
 *    del capital alterado.
 *
 * 2. Bloqueo. lockForUpdate() es no-op en MyISAM, así que dos operaciones
 *    concurrentes sobre el capital pueden pisarse.
 *
 * La tabla tiene una sola fila, así que la conversión es inmediata.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if ($this->currentEngine() === 'InnoDB') {
            return;
        }

        DB::statement('ALTER TABLE `capital_account` ENGINE = InnoDB');
    }

    public function down(): void
    {
        // No-op: volver a MyISAM restauraría la pérdida de atomicidad.
    }

    private function currentEngine(): ?string
    {
        $row = DB::selectOne(
            'SELECT engine FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            ['capital_account']
        );

        return $row->engine ?? $row->ENGINE ?? null;
    }
};
