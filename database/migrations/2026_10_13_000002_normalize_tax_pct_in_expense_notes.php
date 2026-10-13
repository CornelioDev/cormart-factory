<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Escribe con dos decimales el porcentaje en las notas de los gastos de impuesto
 * automáticos: "(0.2%)" pasa a "(0.20%)".
 *
 * El porcentaje se interpolaba como float, así que 0.20 quedaba escrito "0.2"
 * mientras 0.15 salía completo. Desde ahora se formatea con
 * Parameter::formatPct(); esta migración alinea las notas ya guardadas para que
 * el mismo impuesto no aparezca con dos grafías en el listado de transacciones.
 *
 * Solo toca el texto de `notes`. Ni montos ni `updated_at` cambian, y el prefijo
 * por el que GenerateRetroactiveTaxExpenses reconoce estos gastos queda intacto.
 *
 * Idempotente: una nota que ya tiene dos decimales no se modifica.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('transactions')
            ->where('type', 'expense')
            ->where('notes', 'like', 'Impuesto por transacción — Automático (%')
            ->orderBy('id')
            ->select(['id', 'notes'])
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $notes = preg_replace_callback(
                        '/Automático \((\d+(?:\.\d+)?)%\)/u',
                        fn (array $m): string => 'Automático (' . number_format((float) $m[1], 2, '.', '') . '%)',
                        $row->notes,
                        1
                    );

                    if ($notes !== null && $notes !== $row->notes) {
                        DB::table('transactions')->where('id', $row->id)->update(['notes' => $notes]);
                    }
                }
            });
    }

    public function down(): void
    {
        // No-op: es solo la forma de escribir el mismo número.
    }
};
