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
     * El monto se calcula automáticamente:
     *   - disbursement: suma de transfer_amount (lo que el fondo entrega)
     *   - collection:   suma de amount          (lo que el deudor devuelve)
     */
    public function create(array $data, array $financingIds): Transaction
    {
        return DB::transaction(function () use ($data, $financingIds) {
            $financings = Financing::whereIn('id', $financingIds)->get();

            // Monto calculado desde los financiamientos (fuente de verdad)
            $data['amount'] = $data['type'] === 'disbursement'
                ? $financings->sum('transfer_amount')
                : $financings->sum('amount');

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
                $financings->each(fn (Financing $f) => $f->update([
                    'status'            => 'collected',
                    'collected_at'      => $date,
                    'collection_period' => $date->format('Y-m'),
                ]));
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
}
