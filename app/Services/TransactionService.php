<?php

namespace App\Services;

use App\Models\Financing;
use App\Models\FundMember;
use App\Models\Parameter;
use App\Models\Supplier;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    /**
     * Crea una transacción y la vincula a los financiamientos indicados.
     *
     * Para desembolsos: monto = suma de transfer_amount. Se genera automáticamente
     * un gasto de impuesto (tax_pct) sobre el monto.
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

                // Comisión cobrada por adelantado — acreditar al fondo
                $totalCommission = $financings->sum('commission');
                if ($totalCommission > 0) {
                    (new FundAccountService())->credit($totalCommission);
                }

                // Auto-generar gasto de impuesto sobre el monto desembolsado
                $this->createTaxExpense($transaction, $data);
            } elseif ($data['type'] === 'collection') {
                if ($transaction->status === 'confirmed') {
                    $this->applyCollectionToFinancings($transaction);
                } else {
                    // Transacción pendiente de confirmación — marcar financiamientos como pago pendiente
                    $financings->each(fn (Financing $f) => $f->update(['status' => 'pending_payment']));
                }
            }

            return $transaction;
        });
    }

    /**
     * Crea un gasto manual (super_admin).
     */
    public function createExpense(array $data): Transaction
    {
        return DB::transaction(function () use ($data) {
            $transaction = Transaction::create($data);

            $transaction->update([
                'status'       => 'confirmed',
                'confirmed_by' => auth()->id(),
                'confirmed_at' => now(),
            ]);

            // Debitar gasto del fondo
            (new FundAccountService())->debit((float) $transaction->amount);

            return $transaction;
        });
    }

    /**
     * Auto-genera un gasto de impuesto (tax_pct) asociado a un desembolso.
     */
    private function createTaxExpense(Transaction $disbursement, array $disbursementData): void
    {
        $taxPct = (float) Parameter::where('key', 'tax_pct')->value('value');

        if ($taxPct <= 0) {
            return;
        }

        $taxAmount  = round((float) $disbursement->amount * ($taxPct / 100), 2);
        $txCode     = 'TX' . str_pad($disbursement->id, 6, '0', STR_PAD_LEFT);
        $dgii       = Supplier::firstOrCreate(['name' => 'DGII']);
        $registrant = $disbursementData['registered_by'] ?? auth()->id();

        Transaction::create([
            'type'               => 'expense',
            'status'             => 'confirmed',
            'amount'             => $taxAmount,
            'bank'               => $disbursementData['bank'],
            'transaction_number' => $txCode,
            'transaction_date'   => $disbursementData['transaction_date'],
            'supplier_id'        => $dgii->id,
            'notes'              => "Impuesto por transacción — Automático ({$taxPct}%) por desembolso {$txCode}",
            'registered_by'      => $registrant,
            'confirmed_by'       => $registrant,
            'confirmed_at'       => now(),
        ]);

        // Debitar gasto del fondo
        (new FundAccountService())->debit($taxAmount);
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

        if ($transaction->type === 'collection') {
            $this->applyCollectionToFinancings($transaction);
        }

        return $transaction->fresh();
    }

    /**
     * Aplica el cobro a los financiamientos vinculados a la transacción.
     */
    private function applyCollectionToFinancings(Transaction $transaction): void
    {
        $financings = $transaction->financings;
        $date = Carbon::parse($transaction->transaction_date);
        $transactionAmount = (float) $transaction->amount;

        $financings->each(function (Financing $f) use ($date, $transactionAmount, $financings) {
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

    /**
     * Crea una transacción de distribución de ganancias para un miembro (cierre mensual).
     */
    public function createEarningDistribution(int $fundMemberId, float $amount, string $period): Transaction
    {
        return Transaction::create([
            'type'               => 'earning_distribution',
            'status'             => 'confirmed',
            'amount'             => round($amount, 2),
            'transaction_number' => "DIST-{$period}-{$fundMemberId}",
            'transaction_date'   => now(),
            'fund_member_id'     => $fundMemberId,
            'notes'              => "Distribución de ganancias período {$period}",
            'registered_by'      => auth()->id(),
            'confirmed_by'       => auth()->id(),
            'confirmed_at'       => now(),
        ]);
    }

    /**
     * Registra un desembolso de ganancias a un miembro del fondo.
     */
    public function createMemberDisbursement(array $data): Transaction
    {
        return DB::transaction(function () use ($data) {
            $member = FundMember::findOrFail($data['fund_member_id']);
            $amount = (float) $data['amount'];

            if ($amount <= 0) {
                throw new \Exception('El monto debe ser mayor a cero.');
            }

            if ($amount > $member->earningsBalance()) {
                throw new \Exception(
                    'Monto excede el balance de ganancias disponible (RD$ '
                    . number_format($member->earningsBalance(), 2, '.', ',') . ').'
                );
            }

            $transaction = Transaction::create([
                'type'               => 'member_disbursement',
                'status'             => 'confirmed',
                'amount'             => $amount,
                'bank'               => $data['bank'],
                'transaction_number' => $data['transaction_number'],
                'transaction_date'   => $data['transaction_date'],
                'fund_member_id'     => $member->id,
                'notes'              => $data['notes'] ?? "Desembolso de ganancias a {$member->name}",
                'registered_by'      => auth()->id(),
                'confirmed_by'       => auth()->id(),
                'confirmed_at'       => now(),
            ]);

            // Auto-generar gasto de impuesto
            $this->createTaxExpense($transaction, [
                'bank'             => $data['bank'],
                'transaction_date' => $data['transaction_date'],
                'registered_by'    => auth()->id(),
            ]);

            return $transaction;
        });
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
