<?php

namespace App\Services;

use App\Models\Financing;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    /**
     * Crea una transacción y la vincula a los financiamientos indicados.
     *
     * Para desembolsos: monto = suma de transfer_amount.
     * Para cobros: monto puede ser el total (cobro completo) o un monto parcial (abono).
     *   - Si $data['amount'] ya viene definido, se usa ese monto (abono parcial).
     *   - Si no, se calcula desde los financiamientos (cobro completo).
     */
    public function create(array $data, array $financingIds): Transaction
    {
        return DB::transaction(function () use ($data, $financingIds) {
            $financings = Financing::whereIn('id', $financingIds)->get();

            if ($data['type'] === 'disbursement') {
                $data['amount'] = $financings->sum('transfer_amount');
            } elseif (! isset($data['amount']) || $data['amount'] === null) {
                $data['amount'] = $financings->sum('amount');
            }

            // Para cobros, la compañía siempre viene de los financiamientos
            if ($data['type'] === 'collection') {
                $data['company_id'] = $financings->first()?->company_id;
            }

            $transaction = Transaction::create($data);
            $transaction->financings()->sync($financingIds);

            // Los desembolsos y cobros de operadores internos se auto-confirman.
            // Solo los cobros registrados por company_user quedan pendientes.
            $isInternal = auth()->user()->hasAnyRole(['super_admin', 'operator']);
            if ($data['type'] === 'disbursement' || $isInternal) {
                $transaction->update([
                    'status'       => 'confirmed',
                    'confirmed_by' => auth()->id(),
                    'confirmed_at' => now(),
                ]);
            }

            // Actualizar estado de los financiamientos
            if ($data['type'] === 'disbursement') {
                $financings->each(fn (Financing $f) => $f->update([
                    'status'       => 'disbursed',
                    'disbursed_at' => now(),
                    'issue_period' => now()->format('Y-m'),
                ]));
            } elseif ($data['type'] === 'collection') {
                $date = Carbon::parse($data['transaction_date']);
                $transactionAmount = (float) $data['amount'];

                $financings->each(function (Financing $f) use ($date, $transactionAmount, $financings) {
                    // Distribuir monto proporcionalmente si hay múltiples financiamientos
                    $totalRemaining = $financings->sum(fn ($fin) => $fin->remainingBalance());
                    $paymentForThis = $financings->count() === 1
                        ? $transactionAmount
                        : round($transactionAmount * ($f->remainingBalance() / max($totalRemaining, 0.01)), 2);

                    $newCollected = round((float) $f->collected_amount + $paymentForThis, 2);
                    $isFullyPaid  = $newCollected >= (float) $f->amount;

                    $f->update([
                        'collected_amount'  => min($newCollected, (float) $f->amount),
                        'status'            => $isFullyPaid ? 'collected' : 'partially_collected',
                        'collected_at'      => $isFullyPaid ? $date : null,
                        'collection_period' => $isFullyPaid ? $date->format('Y-m') : null,
                    ]);
                });
            }

            return $transaction;
        });
    }

    /**
     * Confirma una transacción pendiente.
     */
    public function confirm(Transaction $transaction): Transaction
    {
        if ($transaction->status !== 'pending') {
            throw new \Exception('Solo se pueden confirmar transacciones en estado pendiente.');
        }

        $transaction->update([
            'status'       => 'confirmed',
            'confirmed_by' => auth()->id(),
            'confirmed_at' => now(),
        ]);

        return $transaction->fresh();
    }

    /**
     * Calcula el monto total de una lista de financiamientos según el tipo de transacción.
     */
    public function calculateAmount(string $type, array $financingIds): float
    {
        if (empty($financingIds)) {
            return 0.0;
        }

        $financings = Financing::whereIn('id', $financingIds)->get();

        return $type === 'disbursement'
            ? (float) $financings->sum('transfer_amount')
            : (float) $financings->sum('amount');
    }

    /**
     * Calcula el balance pendiente de cobro para una lista de financiamientos.
     */
    public function calculateRemainingBalance(array $financingIds): float
    {
        if (empty($financingIds)) {
            return 0.0;
        }

        $financings = Financing::whereIn('id', $financingIds)->get();

        return (float) $financings->sum(fn (Financing $f) => $f->remainingBalance());
    }
}
