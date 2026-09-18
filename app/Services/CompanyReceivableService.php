<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Libro de cuentas por cobrar a compañías.
 *
 * El fondo queda acreedor de una compañía cuando le entrega efectivo que no
 * corresponde a un financiamiento vigente — el caso que originó esto fue el
 * sobre-desembolso de FN000130. Ese saldo no es una cuenta por cobrar al deudor:
 * es riesgo de contraparte contra la compañía, y sin este libro terminaba
 * absorbido por el préstamo automático fondo→capital.
 *
 * Dos movimientos, ambos confirmados al nacer porque los registra personal interno:
 *
 *   company_receivable  +  nace el saldo. No mueve ledgers: el efectivo ya salió
 *                          con el desembolso y capital/fondo ya lo reflejan.
 *   company_repayment   −  la compañía devuelve el efectivo. Acredita capital.
 *
 * Única puerta de entrada a estos dos tipos: nadie más debe crearlos.
 */
class CompanyReceivableService
{
    /**
     * Saldo que la compañía le debe al fondo.
     */
    public function balanceFor(int $companyId): float
    {
        $owed = (float) Transaction::where('type', 'company_receivable')
            ->where('status', 'confirmed')
            ->where('company_id', $companyId)
            ->sum('amount');

        $repaid = (float) Transaction::where('type', 'company_repayment')
            ->where('status', 'confirmed')
            ->where('company_id', $companyId)
            ->sum('amount');

        return round($owed - $repaid, 2);
    }

    /**
     * Saldo total pendiente de todas las compañías.
     */
    public function totalOutstanding(): float
    {
        $owed = (float) Transaction::where('type', 'company_receivable')
            ->where('status', 'confirmed')->sum('amount');

        $repaid = (float) Transaction::where('type', 'company_repayment')
            ->where('status', 'confirmed')->sum('amount');

        return round($owed - $repaid, 2);
    }

    /**
     * Saldo pendiente por compañía, solo las que deben algo.
     *
     * @return Collection<int, array{company: Company, balance: float}>
     */
    public function outstandingByCompany(): Collection
    {
        return Company::orderBy('name')->get()
            ->map(fn (Company $company) => [
                'company' => $company,
                'balance' => $this->balanceFor($company->id),
            ])
            ->filter(fn (array $row) => abs($row['balance']) > 0.001)
            ->values();
    }

    /**
     * Movimientos de una compañía, con saldo corrido.
     */
    public function statementFor(int $companyId): Collection
    {
        $running = 0.0;

        return Transaction::whereIn('type', ['company_receivable', 'company_repayment'])
            ->where('status', 'confirmed')
            ->where('company_id', $companyId)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get()
            ->map(function (Transaction $tx) use (&$running) {
                $signed = $tx->type === 'company_receivable'
                    ? (float) $tx->amount
                    : -(float) $tx->amount;

                $running = round($running + $signed, 2);

                return [
                    'transaction' => $tx,
                    'signed'      => $signed,
                    'balance'     => $running,
                ];
            });
    }

    /**
     * Registra un saldo nuevo a favor del fondo.
     *
     * No mueve capital ni fondo: el efectivo ya salió cuando se entregó de más.
     */
    public function record(array $data): Transaction
    {
        $amount = round((float) ($data['amount'] ?? 0), 2);

        if ($amount <= 0) {
            throw new \Exception('El monto de la cuenta por cobrar debe ser mayor que cero.');
        }

        if (empty($data['company_id'])) {
            throw new \Exception('La cuenta por cobrar debe tener una compañía asociada.');
        }

        $actor = auth()->id() ?? ($data['registered_by'] ?? null);

        return Transaction::create([
            'type'               => 'company_receivable',
            'status'             => 'confirmed',
            'amount'             => $amount,
            'company_id'         => $data['company_id'],
            'transaction_date'   => $data['transaction_date'] ?? Carbon::today(),
            'bank'               => $data['bank'] ?? null,
            'transaction_number' => $data['transaction_number'] ?? null,
            'notes'              => $data['notes'] ?? null,
            'registered_by'      => $actor,
            'confirmed_by'       => $actor,
            'confirmed_at'       => now(),
        ]);
    }

    /**
     * Registra la devolución del efectivo por parte de la compañía.
     *
     * Acredita capital, que es de donde salió el efectivo de más. Bloquea los
     * movimientos de la compañía para que dos devoluciones simultáneas no salden
     * el mismo saldo dos veces.
     */
    public function repay(array $data): Transaction
    {
        $amount = round((float) ($data['amount'] ?? 0), 2);

        if ($amount <= 0) {
            throw new \Exception('El monto de la devolución debe ser mayor que cero.');
        }

        $companyId = $data['company_id'] ?? null;

        if (! $companyId) {
            throw new \Exception('La devolución debe tener una compañía asociada.');
        }

        return DB::transaction(function () use ($amount, $companyId, $data) {
            $balance = $this->lockedBalanceFor((int) $companyId);

            if ($amount > $balance + 0.001) {
                throw new \Exception(sprintf(
                    'La devolución (RD$ %s) supera el saldo pendiente de la compañía (RD$ %s).',
                    number_format($amount, 2, '.', ','),
                    number_format($balance, 2, '.', ',')
                ));
            }

            $actor = auth()->id() ?? ($data['registered_by'] ?? null);

            $transaction = Transaction::create([
                'type'               => 'company_repayment',
                'status'             => 'confirmed',
                'amount'             => $amount,
                'company_id'         => $companyId,
                'transaction_date'   => $data['transaction_date'] ?? Carbon::today(),
                'bank'               => $data['bank'] ?? null,
                'transaction_number' => $data['transaction_number'] ?? null,
                'notes'              => $data['notes'] ?? null,
                'registered_by'      => $actor,
                'confirmed_by'       => $actor,
                'confirmed_at'       => now(),
            ]);

            (new CapitalAccountService())->credit($amount);

            return $transaction;
        });
    }

    /**
     * Saldo leído con bloqueo de fila, para validar devoluciones sin carreras.
     */
    private function lockedBalanceFor(int $companyId): float
    {
        $rows = Transaction::whereIn('type', ['company_receivable', 'company_repayment'])
            ->where('status', 'confirmed')
            ->where('company_id', $companyId)
            ->lockForUpdate()
            ->get(['type', 'amount']);

        $balance = $rows->sum(fn (Transaction $tx) => $tx->type === 'company_receivable'
            ? (float) $tx->amount
            : -(float) $tx->amount);

        return round((float) $balance, 2);
    }
}
