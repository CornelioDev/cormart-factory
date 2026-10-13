<?php

namespace App\Console\Commands\Operations;

use App\Models\Transaction;
use App\Services\TransactionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Corrige el monto de un cobro que todavía está pendiente de confirmación.
 *
 * El caso: un company_user registra el cobro con un monto que no es el del pago
 * real (TX000440 se registró por el saldo completo de FN000130, 151,536.19,
 * cuando el cheque era de 131,770.60). La UI solo permite confirmar un cobro
 * pendiente, no editarlo ni rechazarlo, y confirmarlo tal cual saldaría el
 * financiamiento con dinero que nadie pagó.
 *
 * Por qué es seguro: un cobro pendiente todavía no tocó nada. Ni los ledgers, ni
 * `collected_amount`, ni el libro de saldos de compañías se mueven hasta
 * `TransactionService::confirm()`; lo único que hizo fue dejar los
 * financiamientos en `pending_payment`. Cambiar el monto antes de confirmar hace
 * que la confirmación normal, desde la UI, aplique el monto correcto.
 *
 * Por eso mismo no sirve para un cobro ya confirmado: ahí el monto ya se repartió
 * entre capital y mora y se acreditó; eso es una reversión, no una corrección.
 */
class CorrectPendingCollectionAmount extends OperationCommand
{
    protected $signature = 'ops:correct-pending-collection-amount
        {--transaction= : Código del cobro pendiente, ej. TX000440}
        {--amount= : Monto correcto del pago recibido}
        {--reason= : Motivo de la corrección}';

    protected $description = 'Corrige el monto de un cobro pendiente de confirmación, sin tocar ledgers.';

    protected function perform(): int
    {
        $code      = (string) $this->option('transaction');
        $rawAmount = (string) $this->option('amount');

        if ($code === '' || $rawAmount === '') {
            throw new RuntimeException(
                'Se requieren --transaction y --amount. Ej: --transaction=TX000440 --amount=131770.60'
            );
        }

        if (! is_numeric($rawAmount)) {
            throw new RuntimeException("El monto '{$rawAmount}' no es un número. Use punto decimal y sin separador de miles.");
        }

        $newAmount = round((float) $rawAmount, 2);

        $this->requireBalancedLedgers();

        $transaction = $this->resolveTransaction($code, $newAmount);
        $oldAmount   = round((float) $transaction->amount, 2);
        $totalOwed   = $this->totalOwed($transaction);

        $this->renderPlan($transaction, $oldAmount, $newAmount, $totalOwed);

        if ($this->isDryRun()) {
            $this->newLine();
            $this->warn('DRY RUN — no se escribió nada.');

            return self::SUCCESS;
        }

        if (! $this->confirm('¿Aplicar la corrección?', false)) {
            $this->line('Cancelado por el operador.');

            return self::SUCCESS;
        }

        $reason = (string) ($this->option('reason') ?: 'El monto registrado no coincidía con el pago recibido.');
        $note   = "Monto corregido de {$this->money($oldAmount)} a {$this->money($newAmount)}: {$reason}"
            . ' — Autorizado por ' . $this->actor()->email . '.';

        DB::transaction(function () use ($transaction, $newAmount, $note) {
            // Releer con bloqueo: si alguien confirmó el cobro mientras se mostraba
            // el plan, el monto viejo ya se aplicó y esto dejaría de ser inocuo.
            $locked = Transaction::whereKey($transaction->id)->lockForUpdate()->first();

            if ($locked->status !== 'pending') {
                throw new RuntimeException("{$locked->code} dejó de estar pendiente. No se modificó nada.");
            }

            $locked->update([
                'amount' => $newAmount,
                'notes'  => trim(trim((string) $locked->notes) . ' | ' . $note, ' |'),
            ]);

            $this->assertLedgersBalanced();
        });

        $this->logOperation("{$transaction->code} corregido de {$oldAmount} a {$newAmount}", [
            'transaction' => $transaction->code,
            'old_amount'  => $oldAmount,
            'new_amount'  => $newAmount,
            'financings'  => $transaction->financings->pluck('code')->all(),
        ]);

        $this->newLine();
        $this->info("Listo. {$transaction->code} quedó en {$this->money($newAmount)} y sigue pendiente de confirmación.");
        $this->line('Confírmelo desde la UI como cualquier otro cobro.');

        return self::SUCCESS;
    }

    private function resolveTransaction(string $code, float $newAmount): Transaction
    {
        $transaction = Transaction::with('financings')->where('code', $code)->first();

        if (! $transaction) {
            throw new RuntimeException("No existe la transacción {$code}.");
        }

        if ($transaction->type !== 'collection') {
            throw new RuntimeException("{$code} es de tipo '{$transaction->type}'. Solo se corrigen cobros.");
        }

        if ($transaction->status !== 'pending') {
            throw new RuntimeException(
                "{$code} está en estado '{$transaction->status}'. Solo se puede corregir un cobro pendiente: "
                . 'uno confirmado ya se aplicó a los financiamientos y a los ledgers.'
            );
        }

        if ($transaction->financings->isEmpty()) {
            throw new RuntimeException("{$code} no tiene financiamientos asociados. Requiere revisión manual.");
        }

        if ($newAmount <= 0) {
            throw new RuntimeException('El monto corregido debe ser mayor que cero.');
        }

        if (abs($newAmount - (float) $transaction->amount) < 0.01) {
            throw new RuntimeException("{$code} ya tiene ese monto. No hay nada que corregir.");
        }

        // Lo que exceda la deuda no se aplicaría a ningún financiamiento al
        // confirmar, y la transacción quedaría registrando dinero sin destino.
        $totalOwed = $this->totalOwed($transaction);
        if ($newAmount > $totalOwed + 0.001) {
            throw new RuntimeException(
                'El monto corregido (' . $this->money($newAmount) . ') supera lo adeudado por los financiamientos de '
                . $code . ' (' . $this->money($totalOwed) . ').'
            );
        }

        return $transaction;
    }

    /**
     * Capital pendiente más mora a la fecha del cobro: el tope de lo que la
     * confirmación puede aplicar.
     */
    private function totalOwed(Transaction $transaction): float
    {
        return round((new TransactionService())->calculateTotalOwed(
            $transaction->financings->pluck('id')->all(),
            Carbon::parse($transaction->transaction_date)
        ), 2);
    }

    private function renderPlan(Transaction $transaction, float $oldAmount, float $newAmount, float $totalOwed): void
    {
        $this->newLine();
        $this->info('Plan de corrección');
        $this->table(['Concepto', 'Valor'], [
            ['Cobro', $transaction->code . ' (' . $transaction->status . ', ' . $transaction->payment_method . ')'],
            ['Compañía', $transaction->company?->name ?? $transaction->company_id],
            ['Financiamientos', $transaction->financings->pluck('code')->implode(', ')],
            ['Adeudado a la fecha del cobro', $this->money($totalOwed)],
            ['Monto registrado', $this->money($oldAmount)],
            ['Monto corregido', $this->money($newAmount)],
            ['Quedará pendiente tras confirmar', $this->money(round($totalOwed - $newAmount, 2))],
            ['Movimiento de ledgers', $this->money(0)],
        ]);

        $this->line('El cobro sigue pendiente: los financiamientos y los ledgers se mueven recién al confirmarlo.');
    }
}
