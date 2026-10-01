<?php

namespace App\Services;

use App\Models\CompanyCredit;
use App\Models\Transaction;

/**
 * Libro de saldos de las compañías frente al fondo.
 *
 * Cuando un deudor paga con cheque, el dinero queda en manos de la compañía y
 * nace una deuda hacia el fondo. Ese saldo se consume aplicándolo a un nuevo
 * desembolso o liquidándolo por transferencia.
 *
 * Cada movimiento guarda su composición (capital recuperado / mora), porque es
 * eso lo que decide a qué ledger se acredita el efectivo cuando por fin llega:
 * capital → CapitalAccount, mora → FundAccount. Sin el desglose la mora caería
 * en capital y distorsionaría el reparto del cierre mensual.
 *
 * Única puerta de entrada a la tabla `company_credits`: nadie más debe escribirla.
 */
class CompanyCreditService
{
    /**
     * Saldo actual de la compañía a favor del fondo.
     */
    public function balanceFor(int $companyId): float
    {
        return round((float) CompanyCredit::where('company_id', $companyId)->sum('amount'), 2);
    }

    /**
     * Composición del saldo pendiente: cuánto es capital y cuánto es mora.
     */
    public function compositionFor(int $companyId): array
    {
        $rows = CompanyCredit::where('company_id', $companyId)
            ->selectRaw('COALESCE(SUM(capital_amount), 0) AS capital, COALESCE(SUM(late_fee_amount), 0) AS late_fee')
            ->first();

        return [
            'capital'  => round((float) ($rows->capital ?? 0), 2),
            'late_fee' => round((float) ($rows->late_fee ?? 0), 2),
        ];
    }

    /**
     * Saldo que puede aplicarse a un desembolso: solo la parte de capital.
     *
     * La mora es ganancia del fondo y tiene que llegar como efectivo real; si se
     * reciclara en un desembolso quedaría convertida en capital de trabajo sin
     * que el fondo la haya cobrado nunca, que es justo lo que el préstamo interno
     * fondo→capital modela de forma explícita y auditable.
     */
    public function availableForApplication(int $companyId): float
    {
        return max(0.0, $this->compositionFor($companyId)['capital']);
    }

    /**
     * Registra el saldo generado por un cobro en cheque ya confirmado.
     *
     * El desglose lo provee quien aplica el cobro a los financiamientos, que es
     * donde se calcula el reparto entre capital y mora.
     *
     * Idempotente: si la transacción ya generó su asiento no crea otro, para que
     * confirmar dos veces (o reconfirmar) no duplique el saldo.
     */
    public function accrue(Transaction $transaction, float $capital, float $lateFee): ?CompanyCredit
    {
        if ($transaction->type !== 'collection'
            || $transaction->payment_method !== 'check'
            || $transaction->status !== 'confirmed') {
            return null;
        }

        if ($this->alreadyRecorded($transaction, 'accrual')) {
            return null;
        }

        $capital = round($capital, 2);
        $lateFee = round($lateFee, 2);
        $total   = round($capital + $lateFee, 2);

        if ($total <= 0) {
            return null;
        }

        return CompanyCredit::create([
            'company_id'      => $transaction->company_id,
            'transaction_id'  => $transaction->id,
            'type'            => 'accrual',
            'amount'          => $total,
            'capital_amount'  => $capital,
            'late_fee_amount' => $lateFee,
            'notes'           => trim('Cobro en cheque ' . ($transaction->transaction_number ?? '')),
            'created_by'      => $this->actorFor($transaction),
        ]);
    }

    /**
     * Consume saldo de la compañía aplicándolo a un desembolso.
     *
     * Solo consume capital — ver availableForApplication(). Debe llamarse dentro
     * de una transacción de BD: bloquea los movimientos de la compañía para que
     * dos desembolsos simultáneos no gasten el mismo saldo.
     */
    public function apply(Transaction $transaction, float $amount): CompanyCredit
    {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new \Exception('El saldo a aplicar debe ser mayor que cero.');
        }

        $available = $this->lockedCompositionFor($transaction->company_id)['capital'];

        if ($amount > $available + 0.001) {
            throw new \Exception(sprintf(
                'El saldo a aplicar (RD$ %s) supera el saldo de capital disponible de la compañía (RD$ %s). '
                . 'La mora acumulada no puede aplicarse a un desembolso: debe liquidarse por transferencia.',
                number_format($amount, 2, '.', ','),
                number_format($available, 2, '.', ',')
            ));
        }

        return CompanyCredit::create([
            'company_id'      => $transaction->company_id,
            'transaction_id'  => $transaction->id,
            'type'            => 'application',
            'amount'          => -$amount,
            'capital_amount'  => -$amount,
            'late_fee_amount' => 0,
            'notes'           => trim('Saldo aplicado al desembolso ' . ($transaction->code ?? '')),
            'created_by'      => $this->actorFor($transaction),
        ]);
    }

    /**
     * Registra la liquidación del saldo: la compañía transfiere el efectivo al fondo.
     *
     * El monto se reparte en proporción a la composición pendiente, y el asiento
     * resultante le dice al llamador cuánto acreditar a cada ledger.
     *
     * Idempotente, igual que accrue(): el asiento se crea una sola vez por transacción.
     */
    public function settle(Transaction $transaction): ?CompanyCredit
    {
        if ($transaction->type !== 'settlement' || $transaction->status !== 'confirmed') {
            return null;
        }

        if ($this->alreadyRecorded($transaction, 'settlement')) {
            return null;
        }

        $amount = round((float) $transaction->amount, 2);

        if ($amount <= 0) {
            throw new \Exception('El monto de la liquidación debe ser mayor que cero.');
        }

        $composition = $this->lockedCompositionFor($transaction->company_id);
        $balance     = round($composition['capital'] + $composition['late_fee'], 2);

        if ($amount > $balance + 0.001) {
            throw new \Exception(sprintf(
                'La liquidación (RD$ %s) supera el saldo pendiente de la compañía (RD$ %s).',
                number_format($amount, 2, '.', ','),
                number_format($balance, 2, '.', ',')
            ));
        }

        // Reparto proporcional sobre lo pendiente. El redondeo se absorbe en la
        // parte de capital para que capital + mora == amount exactamente.
        $lateFee = $balance > 0
            ? round($amount * ($composition['late_fee'] / $balance), 2)
            : 0.0;
        $capital = round($amount - $lateFee, 2);

        return CompanyCredit::create([
            'company_id'      => $transaction->company_id,
            'transaction_id'  => $transaction->id,
            'type'            => 'settlement',
            'amount'          => -$amount,
            'capital_amount'  => -$capital,
            'late_fee_amount' => -$lateFee,
            'notes'           => trim('Liquidación por transferencia ' . ($transaction->transaction_number ?? '')),
            'created_by'      => $this->actorFor($transaction),
        ]);
    }

    private function alreadyRecorded(Transaction $transaction, string $type): bool
    {
        return CompanyCredit::where('transaction_id', $transaction->id)
            ->where('type', $type)
            ->exists();
    }

    /**
     * Composición leída con bloqueo de fila, para validar consumos sin condiciones de carrera.
     */
    private function lockedCompositionFor(?int $companyId): array
    {
        if (! $companyId) {
            throw new \Exception('La transacción no tiene compañía asociada.');
        }

        $rows = CompanyCredit::where('company_id', $companyId)
            ->lockForUpdate()
            ->get(['capital_amount', 'late_fee_amount']);

        return [
            'capital'  => round((float) $rows->sum('capital_amount'), 2),
            'late_fee' => round((float) $rows->sum('late_fee_amount'), 2),
        ];
    }

    private function actorFor(Transaction $transaction): int
    {
        return auth()->id() ?? $transaction->registered_by;
    }
}
