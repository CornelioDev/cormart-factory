<?php

namespace App\Filament\Pages;

use App\Models\CapitalAccount;
use App\Models\Financing;
use App\Models\FundAccount;
use App\Models\FundMember;
use App\Models\MonthlyClosing;
use App\Models\Transaction;
use Carbon\Carbon;
use Filament\Pages\Page;

class ReconciliationPage extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Reconciliación';
    protected static ?string $navigationGroup = 'Contabilidad';
    protected static ?string $title           = 'Reconciliación Contable';
    protected static ?int    $navigationSort  = 4;

    protected static string $view = 'filament.pages.reconciliation-page';

    public ?string $selectedPeriod = null;
    public array $checks = [];
    public array $summary = [];
    public array $transactions = [];

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('super_admin');
    }

    public function mount(): void
    {
        $this->selectedPeriod = now()->format('Y-m');
        $this->loadData();
    }

    public function updatedSelectedPeriod(): void
    {
        $this->loadData();
    }

    public function getPeriodOptions(): array
    {
        $closedPeriods = MonthlyClosing::orderByDesc('period')->pluck('period');

        $options = [];
        foreach ($closedPeriods as $period) {
            $options[$period] = Carbon::createFromFormat('Y-m', $period)->translatedFormat('F Y');
        }

        $current = now()->format('Y-m');
        if (! isset($options[$current])) {
            $options = [$current => now()->translatedFormat('F Y') . ' (en curso)'] + $options;
        }

        return $options;
    }

    public function loadData(): void
    {
        $this->loadChecks();
        $this->loadSummary();
        $this->loadTransactions();
    }

    // ── Verificación de Ledgers ──────────────────────────────────────────

    private function loadChecks(): void
    {
        // Check 1: CapitalAccount
        // Incluye in_kind con contribution > 0 (capitalización de ganancias híbrida)
        $totalCapital = (float) FundMember::where('active', true)
            ->where(fn ($q) => $q->where('type', 'capital')
                ->orWhere(fn ($q2) => $q2->where('type', 'in_kind')->where('contribution', '>', 0))
            )
            ->sum('contribution');

        $totalCollectedCapital = (float) Financing::whereNotIn('status', ['solicited', 'cancelled'])
            ->sum('collected_amount');

        $totalDisbursedAmount = (float) Financing::whereNotIn('status', ['solicited', 'cancelled'])
            ->sum('amount');

        $expectedCapital = round($totalCapital + $totalCollectedCapital - $totalDisbursedAmount, 2);
        $actualCapital = (float) CapitalAccount::instance()->balance;

        // Check 2: FundAccount
        $totalCommissions = (float) Financing::whereNotIn('status', ['solicited', 'cancelled'])
            ->sum('commission');

        $totalLateFeeCollected = (float) Financing::sum('late_fee_amount');

        $totalExpenses = (float) Transaction::where('type', 'expense')
            ->where('status', 'confirmed')
            ->sum('amount');

        $totalDistributions = (float) Transaction::where('type', 'earning_distribution')
            ->where('status', 'confirmed')
            ->sum('amount');

        $expectedFund = round($totalCommissions + $totalLateFeeCollected - $totalExpenses - $totalDistributions, 2);
        $actualFund = (float) FundAccount::instance()->balance;

        // Check 3: Financing Integrity
        $collectedOverAmount = Financing::whereRaw('collected_amount > amount')->count();
        $collectedNoDate = Financing::where('status', 'collected')
            ->whereNull('collected_at')->count();
        $disbursedNoPeriod = Financing::whereIn('status', ['disbursed', 'partially_collected', 'collected'])
            ->whereNull('issue_period')->count();
        $integrityIssues = $collectedOverAmount + $collectedNoDate + $disbursedNoPeriod;

        // Check 4: Transaction Balance (desembolsos)
        $txnDisbursements = (float) Transaction::where('type', 'disbursement')
            ->where('status', 'confirmed')
            ->sum('amount');
        $financingTransfers = (float) Financing::whereNotIn('status', ['solicited', 'cancelled'])
            ->sum('transfer_amount');
        $disbursementDiff = round(abs($txnDisbursements - $financingTransfers), 2);

        $this->checks = [
            [
                'name'     => 'Cuenta de Capital',
                'expected' => $expectedCapital,
                'actual'   => $actualCapital,
                'diff'     => round($expectedCapital - $actualCapital, 2),
                'pass'     => abs($expectedCapital - $actualCapital) < 0.01,
                'detail'   => "Aportes ({$totalCapital}) + Cobros capital ({$totalCollectedCapital}) − Desembolsos monto ({$totalDisbursedAmount})",
            ],
            [
                'name'     => 'Cuenta del Fondo',
                'expected' => $expectedFund,
                'actual'   => $actualFund,
                'diff'     => round($expectedFund - $actualFund, 2),
                'pass'     => abs($expectedFund - $actualFund) < 0.01,
                'detail'   => "Comisiones ({$totalCommissions}) + Mora ({$totalLateFeeCollected}) − Gastos ({$totalExpenses}) − Distribuciones ({$totalDistributions})",
            ],
            [
                'name'     => 'Integridad de Financiamientos',
                'expected' => 0,
                'actual'   => $integrityIssues,
                'diff'     => $integrityIssues,
                'pass'     => $integrityIssues === 0,
                'detail'   => "Cobrado > Monto: {$collectedOverAmount} | Cobrado sin fecha: {$collectedNoDate} | Desembolsado sin período: {$disbursedNoPeriod}",
            ],
            [
                'name'     => 'Balance de Desembolsos',
                'expected' => $financingTransfers,
                'actual'   => $txnDisbursements,
                'diff'     => $disbursementDiff,
                'pass'     => $disbursementDiff < 0.01,
                'detail'   => "Transacciones desembolso ({$txnDisbursements}) vs. Financiamientos transfer_amount ({$financingTransfers})",
            ],
        ];
    }

    // ── Resumen del Período ──────────────────────────────────────────────

    private function loadSummary(): void
    {
        [$year, $month] = explode('-', $this->selectedPeriod);

        $periodFilter = fn ($q) => $q->where('status', 'confirmed')
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month);

        $disbursements = (float) Transaction::where('type', 'disbursement')->tap($periodFilter)->sum('amount');
        $collections = (float) Transaction::where('type', 'collection')->tap($periodFilter)->sum('amount');
        $expenses = (float) Transaction::where('type', 'expense')->tap($periodFilter)->sum('amount');
        $distributions = (float) Transaction::where('type', 'earning_distribution')->tap($periodFilter)->sum('amount');
        $memberDisbursements = (float) Transaction::where('type', 'member_disbursement')->tap($periodFilter)->sum('amount');

        $commissions = (float) Financing::whereNotIn('status', ['solicited', 'cancelled'])
            ->where('issue_period', $this->selectedPeriod)
            ->sum('commission');

        $lateFeesCollected = (float) Financing::whereYear('updated_at', $year)
            ->whereMonth('updated_at', $month)
            ->sum('late_fee_amount');

        $totalInflows = $collections + $commissions;
        $totalOutflows = $disbursements + $expenses + $distributions + $memberDisbursements;

        $this->summary = [
            ['concept' => 'Desembolsos', 'inflow' => 0, 'outflow' => $disbursements],
            ['concept' => 'Cobros', 'inflow' => $collections, 'outflow' => 0],
            ['concept' => 'Comisiones (retenidas)', 'inflow' => $commissions, 'outflow' => 0],
            ['concept' => 'Mora cobrada', 'inflow' => $lateFeesCollected, 'outflow' => 0],
            ['concept' => 'Gastos operativos', 'inflow' => 0, 'outflow' => $expenses],
            ['concept' => 'Distribuciones', 'inflow' => 0, 'outflow' => $distributions],
            ['concept' => 'Desembolsos a miembros', 'inflow' => 0, 'outflow' => $memberDisbursements],
            ['concept' => 'TOTAL', 'inflow' => $totalInflows, 'outflow' => $totalOutflows, 'is_total' => true],
        ];
    }

    // ── Detalle de Transacciones ─────────────────────────────────────────

    private function loadTransactions(): void
    {
        [$year, $month] = explode('-', $this->selectedPeriod);

        $txns = Transaction::where('status', 'confirmed')
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month)
            ->with(['company', 'fundMember', 'financings'])
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $this->transactions = $txns->map(fn (Transaction $t) => [
            'date'       => Carbon::parse($t->transaction_date)->format('d/m/Y'),
            'type'       => $this->translateType($t->type),
            'type_raw'   => $t->type,
            'number'     => $t->transaction_number,
            'entity'     => $t->company?->name ?? $t->fundMember?->name ?? '—',
            'amount'     => (float) $t->amount,
            'bank'       => $t->bank ?? '—',
            'financings' => $t->financings->pluck('code')->implode(', ') ?: '—',
        ])->toArray();
    }

    private function translateType(string $type): string
    {
        return match ($type) {
            'disbursement'         => 'Desembolso',
            'collection'           => 'Cobro',
            'expense'              => 'Gasto',
            'earning_distribution' => 'Distribución',
            'member_disbursement'  => 'Desembolso Miembro',
            default                => $type,
        };
    }
}
