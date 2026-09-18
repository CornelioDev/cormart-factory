<?php

use App\Models\Financing;
use App\Models\Transaction;
use App\Services\CompanyReceivableService;
use Illuminate\Database\Migrations\Migration;

/**
 * Registra el saldo que la compañía de FN000130 le debe al fondo.
 *
 * Al corregir el plazo de FN000130 de 15 a 60 días la comisión pasó de 19,765.59
 * a 39,531.18 y el transfer_amount bajó a 355,780.62, pero el fondo ya había
 * desembolsado 375,546.21 en 6 partidas bajo la comisión vieja. Esos 19,765.59 de
 * diferencia son efectivo real que quedó en manos de la compañía.
 *
 * Hasta ahora sólo existían como capital negativo, y el préstamo automático
 * fondo→capital de TransactionService los habría reclasificado como deuda interna
 * en el siguiente desembolso, borrando el rastro de quién debe qué. Este asiento
 * les da un instrumento: saldo contra la compañía, no contra el deudor.
 *
 * No mueve capital ni fondo. El efectivo ya salió y ambos ledgers ya lo reflejan
 * (capital quedó en -19,765.59 tras la corrección del plazo); este registro es la
 * contrapartida de trazabilidad, no un movimiento nuevo.
 *
 * Idempotente: no duplica el asiento si ya existe.
 */
return new class extends Migration
{
    private const FINANCING_ID = 130;
    private const AMOUNT = 19765.59;
    private const MARKER = 'Sobre-desembolso de FN000130';

    public function up(): void
    {
        $financing = Financing::find(self::FINANCING_ID);

        // Sólo tiene sentido si la corrección del plazo ya se aplicó y el
        // sobre-desembolso sigue vigente.
        if (! $financing
            || (int) $financing->term_days !== 60
            || round((float) $financing->disbursed_amount - (float) $financing->transfer_amount, 2) !== self::AMOUNT) {
            return;
        }

        if ($this->alreadyRecorded()) {
            return;
        }

        app(CompanyReceivableService::class)->record([
            'company_id'       => $financing->company_id,
            'amount'           => self::AMOUNT,
            'transaction_date' => '2026-09-18',
            'registered_by'    => $financing->registered_by,
            'notes'            => self::MARKER . ': el plazo se corrigió de 15 a 60 días, '
                . 'la comisión subió a 39,531.18 y el desembolso quedó 19,765.59 por encima '
                . 'del transfer_amount corregido. Efectivo en manos de la compañía.',
        ]);
    }

    public function down(): void
    {
        Transaction::where('type', 'company_receivable')
            ->where('notes', 'like', self::MARKER . '%')
            ->delete();
    }

    private function alreadyRecorded(): bool
    {
        return Transaction::where('type', 'company_receivable')
            ->where('notes', 'like', self::MARKER . '%')
            ->exists();
    }
};
