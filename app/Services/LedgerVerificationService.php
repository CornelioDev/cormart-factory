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

        (new NotificationService())->accountingLedgerError($failed);
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

        (new NotificationService())->accountingLedgerError($failed);
    }

    private function checkCapitalAccount(): array
    {
        $totalCapital = (float) FundMember::where('active', true)
            ->where(fn ($q) => $q->where('type', 'capital')
                ->orWhere(fn ($q2) => $q2->where('type', 'in_kind')->where('contribution', '>', 0))
            )
            ->sum('contribution');

        $totalCollectedCapital = (float) Financing::whereNotIn('status', ['solicited', 'cancelled'])
            ->sum('collected_amount');

        // Capital realmente debitado: físicamente desembolsado (al cliente) + comisión retenida (al fondo).
        // Ambas salen del CapitalAccount al primer desembolso o partidas siguientes.
        $totalDisbursedPhysical = (float) Financing::whereNotIn('status', ['solicited', 'cancelled'])
            ->sum('disbursed_amount');

        $totalCommissionRetained = (float) Financing::whereNotIn('status', ['solicited', 'cancelled'])
            ->sum('commission');

        $totalFundLoan = (float) Transaction::where('type', 'fund_loan_to_capital')
            ->where('status', 'confirmed')->sum('amount');
        $totalRepayment = (float) Transaction::where('type', 'capital_repayment_to_fund')
            ->where('status', 'confirmed')->sum('amount');

        $expected = round(
            $totalCapital + $totalCollectedCapital - $totalDisbursedPhysical - $totalCommissionRetained
            + $totalFundLoan - $totalRepayment,
            2
        );
        $actual = (float) CapitalAccount::instance()->balance;

        return [
            'name'     => 'Cuenta de Capital',
            'expected' => $expected,
            'actual'   => $actual,
            'diff'     => round($expected - $actual, 2),
            'pass'     => abs($expected - $actual) < 0.01,
            'detail'   => "Aportes ({$totalCapital}) + Cobros capital ({$totalCollectedCapital}) − Desembolsado físico ({$totalDisbursedPhysical}) − Comisión retenida ({$totalCommissionRetained}) + Préstamo del fondo ({$totalFundLoan}) − Repago al fondo ({$totalRepayment})",
        ];
    }

    private function checkFundAccount(): array
    {
        $totalCommissions = (float) Financing::whereNotIn('status', ['solicited', 'cancelled'])
            ->sum('commission');

        $totalLateFeeCollected = (float) Financing::sum('late_fee_amount');

        $totalExpenses = (float) Transaction::where('type', 'expense')
            ->where('status', 'confirmed')
            ->sum('amount');

        $totalMemberDisbursements = (float) Transaction::where('type', 'member_disbursement')
            ->where('status', 'confirmed')
            ->sum('amount');

        $totalEarningsToCapital = (float) Transaction::where('type', 'earnings_to_capital')
            ->where('status', 'confirmed')
            ->sum('amount');

        $totalFundLoan = (float) Transaction::where('type', 'fund_loan_to_capital')
            ->where('status', 'confirmed')->sum('amount');
        $totalRepayment = (float) Transaction::where('type', 'capital_repayment_to_fund')
            ->where('status', 'confirmed')->sum('amount');

        $expected = round(
            $totalCommissions + $totalLateFeeCollected
            - $totalExpenses - $totalMemberDisbursements - $totalEarningsToCapital
            - $totalFundLoan + $totalRepayment,
            2
        );
        $actual = (float) FundAccount::instance()->balance;

        return [
            'name'     => 'Cuenta del Fondo',
            'expected' => $expected,
            'actual'   => $actual,
            'diff'     => round($expected - $actual, 2),
            'pass'     => abs($expected - $actual) < 0.01,
            'detail'   => "Comisiones ({$totalCommissions}) + Mora ({$totalLateFeeCollected}) − Gastos ({$totalExpenses}) − Retiros a miembros ({$totalMemberDisbursements}) − Capitalizaciones ({$totalEarningsToCapital}) − Préstamo a capital ({$totalFundLoan}) + Repago desde capital ({$totalRepayment})",
        ];
    }
}
