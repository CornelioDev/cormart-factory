<?php

namespace App\Services;

use App\Models\CapitalAccount;
use App\Models\Financing;
use App\Models\FundAccount;
use App\Models\FundMember;
use App\Models\Parameter;
use App\Models\Supplier;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    private CompanyCreditService $credits;

    public function __construct(?CompanyCreditService $credits = null)
    {
        $this->credits = $credits ?? new CompanyCreditService();
    }

    /**
     * Crea una transacción y la vincula a los financiamientos indicados.
     *
     * Para desembolsos: monto = suma de transfer_amount. Se genera automáticamente
     * un gasto de impuesto (tax_pct) sobre el monto.
     * Para cobros: monto puede ser el total (cobro completo) o un monto parcial (abono).
     *   - Si $data['amount'] ya viene definido, se usa ese monto (abono parcial).
     *   - Si no, se calcula desde los financiamientos (cobro completo).
     *   - Si payment_method es 'check', el efectivo queda en manos de la compañía: no
     *     entra a los ledgers y genera saldo a favor del fondo.
     * Para liquidaciones ('settlement'): la compañía transfiere al fondo el efectivo
     *   que retuvo. No lleva financiamientos y solo la registran usuarios internos.
     */
    public function create(array $data, array $financingIds): Transaction
    {
        return DB::transaction(function () use ($data, $financingIds) {
            $financings = Financing::whereIn('id', $financingIds)->get();

            // El saldo a favor solo tiene sentido en un desembolso; en el resto se
            // normaliza a cero para que no quede un valor colgado en la fila.
            $creditApplied = $data['type'] === 'disbursement'
                ? round((float) ($data['credit_applied'] ?? 0), 2)
                : 0.0;
            $data['credit_applied'] = $creditApplied;

            if ($data['type'] === 'disbursement') {
                // Si hay un solo financiamiento y viene amount explícito, es una partida parcial.
                // Si no, se desembolsa el monto pendiente de cada financiamiento (puede ser múltiple).
                $isPartial = count($financingIds) === 1
                    && isset($data['amount'])
                    && $data['amount'] !== null
                    && (float) $data['amount'] > 0;

                if ($isPartial) {
                    $financing = $financings->first();
                    $remaining = $financing->remainingToDisburse();
                    $disbursementAmount = round((float) $data['amount'], 2);

                    if ($disbursementAmount > $remaining + 0.001) {
                        throw new \Exception(
                            'El monto de la partida (RD$' . number_format($disbursementAmount, 2, '.', ',')
                            . ') excede lo pendiente por desembolsar (RD$' . number_format($remaining, 2, '.', ',') . ').'
                        );
                    }
                } else {
                    $disbursementAmount = (float) $financings->sum(fn (Financing $f) => $f->remainingToDisburse());
                }

                $taxPct    = (float) Parameter::where('key', 'tax_pct')->value('value');
                $taxAmount = $taxPct > 0 ? round($disbursementAmount * ($taxPct / 100), 2) : 0;

                $useFundEarnings = (string) Parameter::where('key', 'allow_fund_loan_to_capital')->value('value') === '1';

                // Comisión retenida del primer desembolso de cada financiamiento. Sale del capital.
                $commissionFirstTime = (float) $financings
                    ->filter(fn (Financing $f) => $f->status === 'solicited')
                    ->sum('commission');

                if ($creditApplied > 0) {
                    if ($creditApplied > $disbursementAmount + 0.001) {
                        throw new \Exception('El saldo aplicado no puede superar el monto a desembolsar.');
                    }

                    $available = $this->credits->availableForApplication((int) $data['company_id']);

                    if ($creditApplied > $available + 0.001) {
                        throw new \Exception(sprintf(
                            'El saldo aplicado (RD$ %s) supera el saldo de capital disponible de la compañía (RD$ %s).',
                            number_format($creditApplied, 2, '.', ','),
                            number_format($available, 2, '.', ',')
                        ));
                    }
                }

                // Efectivo que realmente sale del banco del fondo. La parte cubierta
                // con saldo a favor ya está en manos de la compañía: no se transfiere.
                $cashOut = round($disbursementAmount - $creditApplied, 2);

                $capitalBalance = (float) CapitalAccount::instance()->balance;
                $fundBalance    = (float) FundAccount::instance()->balance;
                $bank           = round($capitalBalance + $fundBalance, 2);
                $bankAfter      = round($bank - $cashOut - $taxAmount, 2);

                $pendingEarnings = round(
                    (float) Transaction::where('type', 'earning_distribution')->where('status', 'confirmed')->sum('amount')
                    - (float) Transaction::whereIn('type', ['member_disbursement', 'earnings_to_capital'])->where('status', 'confirmed')->sum('amount'),
                    2
                );

                if ($bankAfter < -0.001) {
                    throw new \Exception(
                        'El banco total no alcanza el desembolso. Capital RD$'
                        . number_format($capitalBalance, 2, '.', ',') . ' + Fondo RD$'
                        . number_format($fundBalance, 2, '.', ',') . ' = RD$'
                        . number_format($bank, 2, '.', ',') . '. Requerido (con impuesto): RD$'
                        . number_format($cashOut + $taxAmount, 2, '.', ',') . '.'
                    );
                }

                if ($pendingEarnings > 0 && $bankAfter < $pendingEarnings) {
                    throw new \Exception(
                        'El desembolso dejaría el banco en RD$' . number_format($bankAfter, 2, '.', ',')
                        . ', insuficiente para cubrir las ganancias comprometidas a miembros (RD$'
                        . number_format($pendingEarnings, 2, '.', ',') . ').'
                        . ' Déficit: RD$' . number_format($pendingEarnings - $bankAfter, 2, '.', ',') . '.'
                    );
                }

                $requiredCapital = round($cashOut + $commissionFirstTime, 2);

                if ($capitalBalance + 0.001 < $requiredCapital) {
                    if (! $useFundEarnings) {
                        throw new \Exception(
                            'Capital insuficiente para cubrir el desembolso. Capital disponible: RD$'
                            . number_format($capitalBalance, 2, '.', ',') . ', requerido: RD$'
                            . number_format($requiredCapital, 2, '.', ',')
                            . '. Active el parámetro "Permitir préstamo del fondo al capital" en Configuración → Parámetros para usar las ganancias del fondo (RD$'
                            . number_format($fundBalance, 2, '.', ',') . ' disponibles).'
                        );
                    }

                    $shortfall = round($requiredCapital - $capitalBalance, 2);
                    $loanAmount = round(min($shortfall, $fundBalance), 2);

                    if ($loanAmount + 0.001 < $shortfall) {
                        throw new \Exception(
                            'El fondo no tiene suficientes ganancias para cubrir el faltante. Faltante: RD$'
                            . number_format($shortfall, 2, '.', ',') . ', disponible en fondo: RD$'
                            . number_format($fundBalance, 2, '.', ',') . '.'
                        );
                    }

                    $data['_pending_fund_loan'] = $loanAmount;
                }

                $data['amount'] = $disbursementAmount;
            } elseif (! isset($data['amount']) || $data['amount'] === null) {
                $data['amount'] = $financings->sum('amount');
            }

            // Para cobros, la compañía siempre viene de los financiamientos
            if ($data['type'] === 'collection') {
                $data['company_id'] = $financings->first()?->company_id;
            }

            if ($data['type'] === 'settlement') {
                if (! auth()->user()->hasAnyRole(['super_admin', 'operator'])) {
                    throw new \Exception('Solo un operador puede registrar la liquidación de saldo de una compañía.');
                }
                if (empty($data['company_id'])) {
                    throw new \Exception('La liquidación requiere una compañía.');
                }
                if (! isset($data['amount']) || (float) $data['amount'] <= 0) {
                    throw new \Exception('El monto de la liquidación debe ser mayor que cero.');
                }
            }

            $this->assertBankDataPresent(
                $data['type'],
                (float) ($data['amount'] ?? 0),
                $creditApplied,
                $data
            );

            // Garantizar status explícito en el modelo (el default de BD no se refleja en memoria)
            $data['status'] ??= 'pending';

            $pendingFundLoan = (float) ($data['_pending_fund_loan'] ?? 0);
            unset($data['_pending_fund_loan']);

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
                // Préstamo interno del fondo al capital, si el toggle está activo y el capital no alcanza.
                // Se ejecuta antes de aplicar el desembolso para que el débito de capital tenga fondos.
                if ($pendingFundLoan > 0) {
                    $this->createFundLoanToCapital($pendingFundLoan, $transaction);
                }

                // Consumir el saldo antes de debitar capital: si no alcanza, la
                // excepción revierte la transacción sin haber tocado los ledgers.
                if ($creditApplied > 0) {
                    $this->credits->apply($transaction, $creditApplied);
                }

                $this->applyDisbursementToFinancings($transaction, $financings, $disbursementAmount, $isPartial ?? false, $creditApplied);

                // Auto-generar gasto de impuesto sobre el monto desembolsado
                $this->createTaxExpense($transaction, $data);
            } elseif ($data['type'] === 'collection') {
                if ($transaction->status === 'confirmed') {
                    $this->applyCollectionToFinancings($transaction);
                } else {
                    // Transacción pendiente de confirmación — marcar financiamientos como pago pendiente
                    $financings->each(fn (Financing $f) => $f->update(['status' => 'pending_payment']));
                }
            } elseif ($data['type'] === 'settlement' && $transaction->status === 'confirmed') {
                $this->postSettlement($transaction);
            }

            // Notificaciones por email
            $this->sendTransactionNotifications($transaction, $financings);

            return $transaction;
        });
    }

    /**
     * Aplica un desembolso a los financiamientos vinculados.
     *
     * - Si es partida parcial (1 financiamiento, monto explícito): acumula en disbursed_amount.
     * - Si es desembolso completo de varios financiamientos: cada uno recibe lo que le falte
     *   por desembolsar (remainingToDisburse).
     *
     * Comisión: solo se retiene al pasar de solicited → (partially_disbursed | disbursed)
     * por primera vez. issue_period y disbursed_at se fijan en ese momento.
     * due_date NO se asigna aquí: queda bajo control exclusivo de
     * FinancingService::confirmReceipt(), que dispara el plazo cuando el deudor
     * confirma recepción de la mercancía.
     */
    private function applyDisbursementToFinancings(
        Transaction $transaction,
        Collection $financings,
        float $disbursementAmount,
        bool $isPartial,
        float $creditApplied = 0.0
    ): void {
        $totalCommissionFirstTime = 0.0;

        $financings->each(function (Financing $f) use ($disbursementAmount, $isPartial, &$totalCommissionFirstTime) {
            $paymentForThis = $isPartial ? $disbursementAmount : $f->remainingToDisburse();
            if ($paymentForThis <= 0) {
                return;
            }

            $isFirstPartida = $f->status === 'solicited';
            $newDisbursed   = round((float) $f->disbursed_amount + $paymentForThis, 2);
            $fullyDisbursed = $newDisbursed + 0.001 >= (float) $f->transfer_amount;

            // Si al completar el desembolso ya hay cobros parciales acumulados
            // (porque el cliente abonó mientras estaba partially_disbursed), el
            // estado correcto es partially_collected, no disbursed.
            $newStatus = match (true) {
                ! $fullyDisbursed                => 'partially_disbursed',
                (float) $f->collected_amount > 0 => 'partially_collected',
                default                          => 'disbursed',
            };

            $updates = [
                'disbursed_amount' => $fullyDisbursed ? (float) $f->transfer_amount : $newDisbursed,
                'status'           => $newStatus,
            ];

            if ($isFirstPartida) {
                $updates['disbursed_at'] = now();
                $updates['issue_period'] = now()->format('Y-m');
                $totalCommissionFirstTime += (float) $f->commission;
            }

            $f->update($updates);
        });

        if ($totalCommissionFirstTime > 0) {
            (new FundAccountService())->credit($totalCommissionFirstTime);
        }

        // El saldo a favor aplicado se descuenta del débito: esa parte del desembolso
        // la cubrió efectivo que la compañía ya tenía en mano, y el asiento negativo
        // del libro de saldos la da por cobrada. Sin este descuento el capital
        // quedaría debitado por efectivo que nunca salió del banco.
        $capitalDebit = round($disbursementAmount + $totalCommissionFirstTime - $creditApplied, 2);
        if ($capitalDebit > 0) {
            (new CapitalAccountService())->debit($capitalDebit);
        }
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
     * Auto-genera un gasto de impuesto (tax_pct) asociado a un desembolso o retiro.
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

        // En una sola transacción de BD: el libro de saldos bloquea filas para validar
        // consumos, y ese bloqueo solo vale dentro de una transacción abierta.
        return DB::transaction(function () use ($transaction) {
            $transaction->update([
                'status'       => 'confirmed',
                'confirmed_by' => auth()->id(),
                'confirmed_at' => now(),
            ]);

            if ($transaction->type === 'collection') {
                $this->applyCollectionToFinancings($transaction);
            } elseif ($transaction->type === 'settlement') {
                $this->postSettlement($transaction);
            }

            return $transaction->fresh();
        });
    }

    /**
     * Aplica el cobro a los financiamientos vinculados a la transacción.
     * Incluye cálculo de mora proporcional para financiamientos vencidos.
     */
    private function applyCollectionToFinancings(Transaction $transaction): void
    {
        $financings = $transaction->financings;
        $date = Carbon::parse($transaction->transaction_date);
        $transactionAmount = (float) $transaction->amount;
        $financingService = new FinancingService();

        $totalCapitalRecovered = 0;
        $totalLateFeeCollected = 0;

        // Snapshot del balance pendiente ANTES de mutar los financiamientos.
        // Si se calcula dentro del loop, las iteraciones posteriores ven balances
        // ya descontados y el split proporcional sobre-asigna al último financiamiento.
        $totalRemaining = $financings->sum(fn (Financing $fin) => $fin->remainingBalance());
        $payments = $financings->mapWithKeys(fn (Financing $fin) => [
            $fin->id => $financings->count() === 1
                ? $transactionAmount
                : round($transactionAmount * ($fin->remainingBalance() / max($totalRemaining, 0.01)), 2),
        ]);

        $financings->each(function (Financing $f) use ($date, $payments, $financingService, &$totalCapitalRecovered, &$totalLateFeeCollected) {
            $balance = $f->remainingBalance();
            $lateFee = $financingService->calculateLateFee($f, $date);
            $totalOwed = $balance + $lateFee;
            // Cap defensivo: nunca asignar más de lo que el financiamiento debe.
            $paymentForThis = min($payments[$f->id], $totalOwed);

            if ($lateFee > 0 && $totalOwed > 0) {
                // Distribución proporcional entre capital y mora
                $toCapital  = round($paymentForThis * ($balance / $totalOwed), 2);
                $toLateFee  = round($paymentForThis - $toCapital, 2);
            } else {
                $toCapital  = $paymentForThis;
                $toLateFee  = 0.00;
            }

            $totalCapitalRecovered += $toCapital;
            $totalLateFeeCollected += $toLateFee;

            $newCollected     = round((float) $f->collected_amount + $toCapital, 2);
            $isFullyPaid      = $newCollected >= (float) $f->amount;
            $isFullyDisbursed = (float) $f->disbursed_amount + 0.001 >= (float) $f->transfer_amount;

            // Si el financiamiento aún tiene partidas pendientes de desembolso,
            // mantener partially_disbursed aunque ya haya cobros parciales.
            $newStatus = match (true) {
                $isFullyPaid      => 'collected',
                $isFullyDisbursed => 'partially_collected',
                default           => 'partially_disbursed',
            };

            $f->update([
                'collected_amount'  => min($newCollected, (float) $f->amount),
                'late_fee_amount'   => round((float) $f->late_fee_amount + $toLateFee, 2),
                'late_fee_pending'  => round($lateFee - $toLateFee, 2),
                'status'            => $newStatus,
                'collected_at'      => $isFullyPaid ? $date : null,
                'collection_period' => $isFullyPaid ? $date->format('Y-m') : null,
            ]);
        });

        // Cobro en cheque: el deudor pagó a la compañía y el efectivo quedó allí. El
        // financiamiento se salda igual, pero los ledgers no se mueven — el fondo no
        // tiene ese dinero. Queda anotado en el libro de saldos con su composición, y
        // se acreditará cuando la compañía liquide o lo aplique a un desembolso.
        if ($transaction->payment_method === 'check') {
            $this->credits->accrue($transaction, $totalCapitalRecovered, $totalLateFeeCollected);

            return;
        }

        $this->creditCashToLedgers($transaction, $totalCapitalRecovered, $totalLateFeeCollected);
    }

    /**
     * Acredita a los ledgers el efectivo que entró al banco del fondo, separando
     * capital recuperado de mora.
     *
     * Lo usan el cobro por transferencia y la liquidación de saldo de una compañía:
     * en los dos casos el dinero llega de verdad, y el reparto entre capital y
     * ganancias del fondo es el mismo.
     */
    private function creditCashToLedgers(Transaction $transaction, float $capitalRecovered, float $lateFeeCollected): void
    {
        if ($capitalRecovered > 0) {
            (new CapitalAccountService())->credit($capitalRecovered);

            // Repago prioritario al fondo si hay deuda interna vigente.
            $outstanding = $this->outstandingFundLoan();
            if ($outstanding > 0) {
                $repay = round(min($capitalRecovered, $outstanding), 2);
                if ($repay > 0) {
                    $this->createCapitalRepaymentToFund($repay, $transaction);
                }
            }
        }
        if ($lateFeeCollected > 0) {
            (new FundAccountService())->credit($lateFeeCollected);
        }
    }

    /**
     * Saldo vigente de la deuda interna del capital con el fondo.
     */
    public function outstandingFundLoan(): float
    {
        $loaned = (float) Transaction::where('type', 'fund_loan_to_capital')
            ->where('status', 'confirmed')
            ->sum('amount');

        $repaid = (float) Transaction::where('type', 'capital_repayment_to_fund')
            ->where('status', 'confirmed')
            ->sum('amount');

        return round($loaned - $repaid, 2);
    }

    /**
     * Crea una transacción de préstamo interno: el fondo presta cash al capital
     * para cubrir un desembolso. Mueve cash entre ledgers, no afecta el banco real.
     */
    private function createFundLoanToCapital(float $amount, Transaction $disbursement): Transaction
    {
        $loan = Transaction::create([
            'type'               => 'fund_loan_to_capital',
            'status'             => 'confirmed',
            'amount'             => round($amount, 2),
            'transaction_date'   => $disbursement->transaction_date,
            'notes'              => "Préstamo del fondo al capital para desembolso {$disbursement->code}",
            'registered_by'      => auth()->id() ?? $disbursement->registered_by,
            'confirmed_by'       => auth()->id() ?? $disbursement->registered_by,
            'confirmed_at'       => now(),
        ]);

        (new FundAccountService())->debit($amount);
        (new CapitalAccountService())->credit($amount);

        return $loan;
    }

    /**
     * Crea una transacción de repago: el capital devuelve al fondo lo prestado,
     * usando cash recuperado en un cobro. Mueve cash entre ledgers, no afecta el banco real.
     */
    private function createCapitalRepaymentToFund(float $amount, Transaction $collection): Transaction
    {
        $repayment = Transaction::create([
            'type'               => 'capital_repayment_to_fund',
            'status'             => 'confirmed',
            'amount'             => round($amount, 2),
            'transaction_date'   => $collection->transaction_date,
            'notes'              => "Repago del capital al fondo desde cobro {$collection->code}",
            'registered_by'      => auth()->id() ?? $collection->registered_by,
            'confirmed_by'       => auth()->id() ?? $collection->registered_by,
            'confirmed_at'       => now(),
        ]);

        (new CapitalAccountService())->debit($amount);
        (new FundAccountService())->credit($amount);

        return $repayment;
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
        $member = FundMember::findOrFail($data['fund_member_id']);

        $transaction = DB::transaction(function () use ($data, $member) {
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

            // El cash sale del banco al fondo en este momento — ese es el evento real.
            (new FundAccountService())->debit($amount);

            // Impuesto a DGII sobre el retiro: se registra como gasto y se debita del fondo.
            $this->createTaxExpense($transaction, [
                'bank'             => $data['bank'],
                'transaction_date' => $data['transaction_date'],
                'registered_by'    => auth()->id(),
            ]);

            return $transaction;
        });

        rescue(fn () => app(NotificationService::class)->memberDisbursementCreated($transaction, $member));

        return $transaction;
    }

    /**
     * Transfiere ganancias de un miembro a su capital aportado.
     */
    public function createEarningsToCapitalTransfer(array $data): Transaction
    {
        $member = FundMember::findOrFail($data['fund_member_id']);

        return DB::transaction(function () use ($data, $member) {
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
                'type'             => 'earnings_to_capital',
                'status'           => 'confirmed',
                'amount'           => $amount,
                'transaction_date' => $data['transaction_date'],
                'fund_member_id'   => $member->id,
                'notes'            => $data['notes'] ?? "Capitalización de ganancias — {$member->name}",
                'registered_by'    => auth()->id(),
                'confirmed_by'     => auth()->id(),
                'confirmed_at'     => now(),
            ]);

            $member->increment('contribution', $amount);
            (new FundMemberService())->recalculateAllPercentages();

            // Reclasificación: las ganancias del miembro pasan a ser parte de su capital.
            // El cash sigue en el banco; solo cambia su "dueño" entre los ledgers internos.
            (new FundAccountService())->debit($amount);
            (new CapitalAccountService())->credit($amount);

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
            ? (float) $financings->sum(fn (Financing $f) => $f->remainingToDisburse())
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

    /**
     * Calcula el total adeudado (capital + mora estimada) para una lista de financiamientos.
     */
    public function calculateTotalOwed(array $financingIds, Carbon $asOfDate = null): float
    {
        if (empty($financingIds)) {
            return 0.0;
        }

        $asOfDate = $asOfDate ?? Carbon::now();
        $financings = Financing::whereIn('id', $financingIds)->get();
        $service = new FinancingService();

        return (float) $financings->sum(fn (Financing $f) => $f->remainingBalance() + $service->calculateLateFee($f, $asOfDate));
    }

    /**
     * Asienta la liquidación de saldo de una compañía: el efectivo entró, así que se
     * acredita a los ledgers según la composición que el libro de saldos devuelve.
     */
    private function postSettlement(Transaction $transaction): void
    {
        $movement = $this->credits->settle($transaction);

        if (! $movement) {
            return;
        }

        // El movimiento se guarda con signo negativo (consume saldo); a los ledgers
        // entra en positivo.
        $this->creditCashToLedgers(
            $transaction,
            round(abs((float) $movement->capital_amount), 2),
            round(abs((float) $movement->late_fee_amount), 2)
        );
    }

    /**
     * Saldo de la compañía que puede aplicarse a un desembolso (solo capital).
     */
    public function availableCredit(?int $companyId): float
    {
        return $companyId ? $this->credits->availableForApplication($companyId) : 0.0;
    }

    /**
     * Banco y número solo se exigen cuando hay movimiento bancario real: un desembolso
     * cubierto al 100% con saldo a favor no genera transferencia.
     */
    private function assertBankDataPresent(string $type, float $amount, float $creditApplied, array $data): void
    {
        if (! in_array($type, ['disbursement', 'collection', 'settlement'], true)) {
            return;
        }

        $hasBankMovement = $type !== 'disbursement' || round($amount - $creditApplied, 2) > 0;

        if ($hasBankMovement && (empty($data['bank']) || empty($data['transaction_number']))) {
            throw new \Exception('El banco y el número de transacción son obligatorios.');
        }
    }

    /**
     * Envía notificaciones por email según el tipo de transacción.
     */
    private function sendTransactionNotifications(Transaction $transaction, Collection $financings): void
    {
        $notificationService = app(NotificationService::class);

        if ($transaction->type === 'disbursement') {
            rescue(fn () => $notificationService->financingDisbursed($transaction, $financings), report: true);
        } elseif ($transaction->type === 'collection' && $transaction->status === 'pending') {
            rescue(fn () => $notificationService->pendingCollectionCreated($transaction), report: true);
        }
    }
}
