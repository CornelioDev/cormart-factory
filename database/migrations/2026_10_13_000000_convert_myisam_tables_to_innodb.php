<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Convierte a InnoDB las tablas que siguen en MyISAM, empezando por
 * `company_credits`.
 *
 * Es el mismo defecto que 2026_09_18_000000_convert_capital_account_to_innodb
 * corrigió para `capital_account`: el servidor de producción crea las tablas en
 * MyISAM por defecto, y MyISAM no participa en transacciones ni bloquea filas.
 *
 * En `company_credits` eso rompe lo que CompanyCreditService da por sentado:
 *
 * 1. Atomicidad. TransactionService::confirm() envuelve el cobro en cheque en
 *    DB::transaction(); si algo lanza después de accrue(), el financiamiento y la
 *    transacción revierten pero el `accrual` queda escrito. La invariante resta
 *    ese saldo del capital esperado y acusa un descuadre por el monto del cobro.
 *    Verificado sobre una copia de producción: confirmar TX000440 y revertir dejó
 *    la fila, y el capital pasó a descuadrar por 151,536.19.
 *
 * 2. Bloqueo. apply() y settle() bloquean filas para validar que el saldo alcance;
 *    en MyISAM lockForUpdate() es no-op y dos consumos concurrentes pueden pisarse.
 *
 * Las otras tablas MyISAM (configuración de correo y de notificaciones) no son
 * contables, pero se convierten igual para no dejar tablas a medio camino. Las
 * nuevas ya nacen en InnoDB: config/database.php fija el motor.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $tables = DB::select(
            "SELECT table_name AS name FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' AND engine = 'MyISAM'"
        );

        foreach ($tables as $table) {
            DB::statement("ALTER TABLE `{$table->name}` ENGINE = InnoDB");
        }
    }

    public function down(): void
    {
        // No-op: volver a MyISAM restauraría la pérdida de atomicidad.
    }
};
