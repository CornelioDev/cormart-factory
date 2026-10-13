<?php

namespace App\Console\Commands\Operations;

use App\Models\Financing;
use App\Models\Transaction;
use App\Services\CompanyReceivableService;
use App\Services\FinancingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Reclasifica una devolución de compañía (`company_repayment`) como cobro de un
 * financiamiento, cuando el efectivo en realidad lo pagó el deudor.
 *
 * El caso: el deudor de FN000130 pagó su factura en tres cuotas iguales de
 * 131,770.60. La primera transferencia se partió al registrarla: 19,765.59 como
 * devolución del sobre-desembolso de la compañía (TX000397) y el resto como
 * cobro (TX000398). Pero ese dinero era del deudor: la compañía nunca devolvió
 * nada, y el financiamiento quedó mostrando un saldo que el deudor ya no debía.
 *
 * Lo que hace: la transacción pasa a ser un cobro colgado del financiamiento, y
 * su monto se suma a `collected_amount`. Al dejar de existir la devolución, el
 * saldo del libro de cuentas por cobrar a compañías vuelve a quedar abierto por
 * ese monto, que es lo que la compañía sigue debiendo de verdad.
 *
 * Los ledgers no se mueven. El efectivo entró al banco una sola vez y ya está
 * acreditado al capital; solo cambia por qué concepto lo cuenta la invariante:
 *
 *   capital esperado = … + cobros (+monto) … + devoluciones de compañías (−monto)
 *
 * Restricción: el financiamiento no puede tener mora a la fecha de la
 * transacción. Un cobro con mora se reparte entre capital y fondo, y la
 * devolución original acreditó todo a capital; eso ya no es una reclasificación.
 */
class ReclassifyRepaymentAsCollection extends OperationCommand
{
    protected $signature = 'ops:reclassify-repayment-as-collection
        {--transaction= : Código de la devolución de compañía, ej. TX000397}
        {--financing= : Código del financiamiento al que realmente se aplicó el pago}
        {--reason= : Motivo de la reclasificación}';

    protected $description = 'Reclasifica una devolución de compañía como cobro de un financiamiento, sin mover ledgers.';

    protected function perform(): int
    {
        $txCode  = (string) $this->option('transaction');
        $finCode = (string) $this->option('financing');

        if ($txCode === '' || $finCode === '') {
            throw new RuntimeException(
                'Se requieren --transaction y --financing. Ej: --transaction=TX000397 --financing=FN000130'
            );
        }

        $this->requireBalancedLedgers();

        [$transaction, $financing] = $this->resolve($txCode, $finCode);

        $amount       = round((float) $transaction->amount, 2);
        $newCollected = round((float) $financing->collected_amount + $amount, 2);
        $newStatus    = $this->statusAfter($financing, $newCollected);

        $receivables   = new CompanyReceivableService();
        $balanceBefore = $receivables->balanceFor((int) $transaction->company_id);

        $this->renderPlan($transaction, $financing, $amount, $newCollected, $newStatus, $balanceBefore);

        if ($this->isDryRun()) {
            $this->newLine();
            $this->warn('DRY RUN — no se escribió nada.');

            return self::SUCCESS;
        }

        if (! $this->confirm('¿Aplicar la reclasificación?', false)) {
            $this->line('Cancelado por el operador.');

            return self::SUCCESS;
        }

        $reason = (string) ($this->option('reason') ?: 'El pago lo hizo el deudor, no la compañía.');
        $note   = "Reclasificado de devolución de compañía a cobro de {$financing->code}: {$reason}"
            . ' — Autorizado por ' . $this->actor()->email . '.';

        DB::transaction(function () use ($transaction, $financing, $newCollected, $newStatus, $note) {
            $transaction->update([
                'type'  => 'collection',
                'notes' => trim(trim((string) $transaction->notes) . ' | ' . $note, ' |'),
            ]);
            $transaction->financings()->syncWithoutDetaching([$financing->id]);

            $isFullyPaid = $newStatus === 'collected';
            $date        = Carbon::parse($transaction->transaction_date);

            $financing->update([
                'collected_amount'  => $newCollected,
                'status'            => $newStatus,
                'collected_at'      => $isFullyPaid ? $date : null,
                'collection_period' => $isFullyPaid ? $date->format('Y-m') : null,
            ]);

            $this->assertLedgersBalanced();
        });

        $this->logOperation("{$transaction->code} reclasificado como cobro de {$financing->code}", [
            'transaction' => $transaction->code,
            'financing'   => $financing->code,
            'amount'      => $amount,
            'company_id'  => $transaction->company_id,
        ]);

        $this->newLine();
        $this->info("Listo. {$transaction->code} es ahora un cobro de {$financing->code}.");
        $this->line(
            'La compañía vuelve a deber ' . $this->money($receivables->balanceFor((int) $transaction->company_id))
            . ' al fondo. Los ledgers quedaron cuadrados.'
        );

        return self::SUCCESS;
    }

    /**
     * Carga y valida la devolución y el financiamiento.
     *
     * @return array{0: Transaction, 1: Financing}
     */
    private function resolve(string $txCode, string $finCode): array
    {
        $transaction = Transaction::where('code', $txCode)->first();
        $financing   = Financing::where('code', $finCode)->first();

        if (! $transaction) {
            throw new RuntimeException("No existe la transacción {$txCode}.");
        }

        if (! $financing) {
            throw new RuntimeException("No existe el financiamiento {$finCode}.");
        }

        if ($transaction->type !== 'company_repayment' || $transaction->status !== 'confirmed') {
            throw new RuntimeException(
                "{$txCode} es '{$transaction->type}' en estado '{$transaction->status}'. "
                . 'Solo se reclasifica una devolución de compañía confirmada.'
            );
        }

        if ((int) $transaction->company_id !== (int) $financing->company_id) {
            throw new RuntimeException("{$txCode} y {$finCode} no son de la misma compañía.");
        }

        $open = ['partially_disbursed', 'disbursed', 'partially_collected', 'pending_payment'];
        if (! in_array($financing->status, $open, true)) {
            throw new RuntimeException(
                "{$finCode} está en estado '{$financing->status}'. Debe estar desembolsado y con saldo por cobrar."
            );
        }

        $amount    = round((float) $transaction->amount, 2);
        $remaining = $financing->remainingBalance();

        if ($amount > $remaining + 0.001) {
            throw new RuntimeException(
                "El monto de {$txCode} (" . $this->money($amount) . ") supera el saldo de {$finCode} ("
                . $this->money($remaining) . ').'
            );
        }

        // Un cobro con mora se reparte entre capital y fondo; la devolución
        // acreditó todo a capital. Reclasificar ahí dejaría la mora sin asentar.
        $lateFee = (new FinancingService())->calculateLateFee($financing, Carbon::parse($transaction->transaction_date));
        if ($lateFee > 0 || round((float) $financing->late_fee_pending, 2) > 0) {
            throw new RuntimeException("{$finCode} tiene mora a la fecha de {$txCode}. Requiere revisión manual.");
        }

        // Si hay un cobro esperando confirmación por más de lo que quedará de
        // saldo, confirmarlo después registraría dinero sin destino.
        $pending = round((float) $financing->transactions()
            ->where('type', 'collection')
            ->where('status', 'pending')
            ->sum('amount'), 2);

        if ($pending > round($remaining - $amount, 2) + 0.001) {
            throw new RuntimeException(
                "{$finCode} tiene cobros pendientes de confirmar por " . $this->money($pending)
                . ', más que el saldo que quedará (' . $this->money(round($remaining - $amount, 2)) . '). '
                . 'Corrija primero ese cobro con ops:correct-pending-collection-amount.'
            );
        }

        return [$transaction, $financing];
    }

    /**
     * Mismo criterio que TransactionService al aplicar un cobro, salvo que un
     * financiamiento con un cobro esperando confirmación se queda como está:
     * será esa confirmación la que resuelva su estado.
     */
    private function statusAfter(Financing $financing, float $newCollected): string
    {
        if ($financing->status === 'pending_payment') {
            return 'pending_payment';
        }

        if ($newCollected >= (float) $financing->amount) {
            return 'collected';
        }

        return (float) $financing->disbursed_amount + 0.001 >= (float) $financing->transfer_amount
            ? 'partially_collected'
            : 'partially_disbursed';
    }

    private function renderPlan(
        Transaction $transaction,
        Financing $financing,
        float $amount,
        float $newCollected,
        string $newStatus,
        float $balanceBefore
    ): void {
        $this->newLine();
        $this->info('Plan de reclasificación');
        $this->table(['Concepto', 'Valor'], [
            ['Transacción', $transaction->code . ' (devolución de compañía → cobro)'],
            ['Fecha y referencia', $transaction->transaction_date->toDateString() . ' / ' . ($transaction->transaction_number ?? '—')],
            ['Compañía', $transaction->company?->name ?? $transaction->company_id],
            ['Monto', $this->money($amount)],
            ['Financiamiento', $financing->code . ' (' . $financing->status . ' → ' . $newStatus . ')'],
            ['Cobrado', $this->money((float) $financing->collected_amount) . ' → ' . $this->money($newCollected)],
            ['Saldo del deudor', $this->money($financing->remainingBalance()) . ' → ' . $this->money(round((float) $financing->amount - $newCollected, 2))],
            ['Saldo que debe la compañía', $this->money($balanceBefore) . ' → ' . $this->money(round($balanceBefore + $amount, 2))],
            ['Movimiento de ledgers', $this->money(0)],
        ]);
    }
}
