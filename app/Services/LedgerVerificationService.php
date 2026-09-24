<?php

namespace App\Services;

use App\Models\CapitalAccount;
use App\Models\Financing;
use App\Models\FundAccount;
use App\Models\FundMember;
use App\Models\Transaction;
use Illuminate\Support\Facades\Cache;

class LedgerVerificationService
{
    /**
     * Ejecuta las verificaciones contables y devuelve los checks fallidos.
     */
    public function runChecks(): array
    {
        $failed = [];

        $capitalCheck = $this->checkCapitalAccount();
        if (! $capitalCheck['pass']) {
            $failed[] = $capitalCheck;
        }

        $fundCheck = $this->checkFundAccount();
        if (! $fundCheck['pass']) {
            $failed[] = $fundCheck;
        }

        return $failed;
    }

    /**
     * Verifica y notifica si hay errores. Throttle de 1 hora para evitar spam.
     */
    public function verifyAndNotify(): void
    {
        if (Cache::has('ledger_error_notified')) {
            return;
        }

        $failed = $this->runChecks();

        if (empty($failed)) {
            return;
        }

        Cache::put('ledger_error_notified', true, now()->addHour());

        app(NotificationService::class)->accountingLedgerError($failed);
    }

    /**
     * Verifica y notifica sin respetar throttle (para recordatorio diario).
     */
    public function verifyAndNotifyForced(): void
    {
        $failed = $this->runChecks();

        if (empty($failed)) {
            return;
        }

        app(NotificationService::class)->accountingLedgerError($failed);
    }

    /**
     * Componentes de la invariante del CapitalAccount.
     *
     * Única fuente de verdad de la fórmula: ReconciliationPage, DiagnoseLedgers y
     * RecalculateLedgers leen de aquí en vez de repetirla. La duplicación ya costó
     * caro: al agregar el libro de cuentas por cobrar a compañías se actualizó solo
     * esta copia, y la Reconciliación reportó un descuadre falso de 19,765.59
     * mientras RecalculateLedgers habría borrado el movimiento al reescribir el
     * balance. No volver a duplicarla.
     */
    public function capitalBreakdown(): array
    {
        $activeFinancings = fn () => Financing::whereNotIn('status', ['solicited', 'cancelled']);
        $confirmed = fn (string $type) => (float) Transaction::where('type', $type)
            ->where('status', 'confirmed')->sum('amount');

        // Incluye in_kind con contribution > 0 (capitalización de ganancias híbrida)
        $contributions = (float) FundMember::where('active', true)
            ->where(fn ($q) => $q->where('type', 'capital')
                ->orWhere(fn ($q2) => $q2->where('type', 'in_kind')->where('contribution', '>', 0))
            )
            ->sum('contribution');

        $collected = (float) $activeFinancings()->sum('collected_amount');

        // Capital realmente debitado: físicamente desembolsado (al cliente) + comisión retenida (al fondo).
        // Ambas salen del CapitalAccount al primer desembolso o partidas siguientes.
        $disbursedPhysical  = (float) $activeFinancings()->sum('disbursed_amount');
        $commissionRetained = (float) $activeFinancings()->sum('commission');

        $fundLoan      = $confirmed('fund_loan_to_capital');
        $fundRepayment = $confirmed('capital_repayment_to_fund');

        // Efectivo que una compañía devuelve tras un sobre-desembolso. Entra al
        // capital, que es de donde salió de más. El asiento que crea el saldo
        // (company_receivable) no aparece aquí a propósito: no mueve cash, el
        // desembolso ya lo había debitado.
        $companyRepayments = $confirmed('company_repayment');

        $expected = round(
            $contributions + $collected - $disbursedPhysical - $commissionRetained
            + $fundLoan - $fundRepayment + $companyRepayments,
            2
        );

        return [
            'contributions'      => $contributions,
            'collected'          => $collected,
            'disbursedPhysical'  => $disbursedPhysical,
            'commissionRetained' => $commissionRetained,
            'fundLoan'           => $fundLoan,
            'fundRepayment'      => $fundRepayment,
            'companyRepayments'  => $companyRepayments,
            'expected'           => $expected,
            'detail'             => "Aportes ({$contributions}) + Cobros capital ({$collected}) − Desembolsado físico ({$disbursedPhysical}) − Comisión retenida ({$commissionRetained}) + Préstamo del fondo ({$fundLoan}) − Repago al fondo ({$fundRepayment}) + Devoluciones de compañías ({$companyRepayments})",
        ];
    }

    private function checkCapitalAccount(): array
    {
        $b      = $this->capitalBreakdown();
        $actual = (float) CapitalAccount::instance()->balance;

        return [
            'name'     => 'Cuenta de Capital',
            'expected' => $b['expected'],
            'actual'   => $actual,
            'diff'     => round($b['expected'] - $actual, 2),
            'pass'     => abs($b['expected'] - $actual) < 0.01,
            'detail'   => $b['detail'],
        ];
    }

    /**
     * Componentes de la invariante del FundAccount. Misma regla que
     * capitalBreakdown(): única fuente de verdad, no duplicar la fórmula.
     */
    public function fundBreakdown(): array
    {
        $confirmed = fn (string $type) => (float) Transaction::where('type', $type)
            ->where('status', 'confirmed')->sum('amount');

        $commissions = (float) Financing::whereNotIn('status', ['solicited', 'cancelled'])
            ->sum('commission');

        $lateFeeCollected   = (float) Financing::sum('late_fee_amount');
        $expenses           = $confirmed('expense');
        $memberDisbursement = $confirmed('member_disbursement');
        $earningsToCapital  = $confirmed('earnings_to_capital');
        $fundLoan           = $confirmed('fund_loan_to_capital');
        $fundRepayment      = $confirmed('capital_repayment_to_fund');

        $expected = round(
            $commissions + $lateFeeCollected
            - $expenses - $memberDisbursement - $earningsToCapital
            - $fundLoan + $fundRepayment,
            2
        );

        return [
            'commissions'        => $commissions,
            'lateFeeCollected'   => $lateFeeCollected,
            'expenses'           => $expenses,
            'memberDisbursement' => $memberDisbursement,
            'earningsToCapital'  => $earningsToCapital,
            'fundLoan'           => $fundLoan,
            'fundRepayment'      => $fundRepayment,
            'expected'           => $expected,
            'detail'             => "Comisiones ({$commissions}) + Mora ({$lateFeeCollected}) − Gastos ({$expenses}) − Retiros a miembros ({$memberDisbursement}) − Capitalizaciones ({$earningsToCapital}) − Préstamo a capital ({$fundLoan}) + Repago desde capital ({$fundRepayment})",
        ];
    }

    private function checkFundAccount(): array
    {
        $b      = $this->fundBreakdown();
        $actual = (float) FundAccount::instance()->balance;

        return [
            'name'     => 'Cuenta del Fondo',
            'expected' => $b['expected'],
            'actual'   => $actual,
            'diff'     => round($b['expected'] - $actual, 2),
            'pass'     => abs($b['expected'] - $actual) < 0.01,
            'detail'   => $b['detail'],
        ];
    }
}
