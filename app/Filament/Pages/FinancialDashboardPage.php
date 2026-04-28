<?php

namespace App\Filament\Pages;

use App\Models\CapitalAccount;
use App\Models\Financing;
use App\Models\FundAccount;
use App\Models\FundMember;
use App\Models\MonthlyClosing;
use App\Models\Transaction;
use App\Services\DistributionService;
use Carbon\Carbon;
use Filament\Pages\Page;

class FinancialDashboardPage extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-chart-bar-square';
    protected static ?string $navigationLabel = 'Dashboard Financiero';
    protected static ?string $title           = 'Dashboard Financiero';
    protected static ?int    $navigationSort  = 0;

    protected static string $view = 'filament.pages.financial-dashboard-page';

    public ?string $selectedPeriod = null;
    public ?array $snapshot = null;
    public array $chartData = [];
    public array $roiChartData = [];
    public array $financingsChartData = [];
    public array $cashflowChartData = [];

    public static function canAccess(): bool
    {
        return auth()->user()->can('page_FinancialDashboardPage');
    }

    public function mount(): void
    {
        $this->selectedPeriod = now()->format('Y-m');
        $this->loadSnapshot();
        $this->chartData = $this->getChartData();
        $this->roiChartData = $this->getRoiChartData();
        $this->financingsChartData = $this->getFinancingsChartData();
        $this->cashflowChartData = $this->getCashflowChartData();
    }

    public function updatedSelectedPeriod(): void
    {
        $this->loadSnapshot();
        $this->cashflowChartData = $this->getCashflowChartData();
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

    public function loadSnapshot(): void
    {
        if (! $this->selectedPeriod) {
            return;
        }

        $closing = MonthlyClosing::where('period', $this->selectedPeriod)
            ->with('distributions.fundMember')
            ->first();

        if ($closing) {
            $distributions = $closing->distributions->map(fn ($d) => [
                'fund_member_id'      => $d->fund_member_id,
                'name'                => $d->fundMember->name,
                'type'                => $d->fundMember->type,
                'fixed_amount'        => (float) $d->fixed_amount,
                'proportional_amount' => (float) $d->proportional_amount,
                'total_amount'        => (float) $d->total_amount,
            ])->toArray();

            $this->snapshot = [
                'total_commissions'     => (float) $closing->total_commissions,
                'total_expenses'        => (float) $closing->total_expenses,
                'total_fixed'           => (float) $closing->total_fixed,
                'net_profit'            => (float) $closing->net_profit,
                'reserve'               => (float) $closing->reserve,
                'post_reserve'          => (float) $closing->post_reserve,
                'in_kind_payment'       => (float) $closing->in_kind_payment,
                'available_for_capital' => (float) $closing->available_for_capital,
                'distributions'         => $distributions,
                'is_closed'             => true,
            ];
        } else {
            $data = (new DistributionService())->calculate($this->selectedPeriod);

            $this->snapshot = [
                'total_commissions'     => (float) $data['total_commissions'],
                'total_expenses'        => (float) $data['total_expenses'],
                'total_fixed'           => (float) $data['total_fixed'],
                'net_profit'            => (float) $data['net_profit'],
                'reserve'               => (float) $data['reserve'],
                'post_reserve'          => (float) $data['post_reserve'],
                'in_kind_payment'       => (float) $data['in_kind_payment'],
                'available_for_capital' => (float) $data['available_for_capital'],
                'distributions'         => $data['distributions'],
                'is_closed'             => false,
            ];
        }

        // KPIs operativos. Estados con capital del fondo afuera (físicamente desembolsado).
        $outstandingStatuses = ['partially_disbursed', 'disbursed', 'partially_collected'];

        $capitalInStreet = (float) Financing::whereIn('status', $outstandingStatuses)
            ->selectRaw('SUM(disbursed_amount - collected_amount) as pending')
            ->value('pending');
        $this->snapshot['capital_in_street'] = $capitalInStreet;

        // Incluye in_kind con contribution > 0 (capitalización de ganancias híbrida)
        $totalCapital = (float) FundMember::where('active', true)
            ->where(fn ($q) => $q->where('type', 'capital')
                ->orWhere(fn ($q2) => $q2->where('type', 'in_kind')->where('contribution', '>', 0))
            )
            ->sum('contribution');
        $this->snapshot['capital_total'] = $totalCapital;

        // Saldo estimado del banco: suma de los dos ledgers reales (capital + fondo).
        // ReconciliationPage garantiza que estos balances reflejan el cash real.
        $fundBalance    = (float) FundAccount::instance()->balance;
        $capitalBalance = (float) CapitalAccount::instance()->balance;

        $this->snapshot['fund_balance']       = $fundBalance;
        $this->snapshot['estimated_bank']     = round($capitalBalance + $fundBalance, 2);
        $this->snapshot['capital_available']  = max(0, $capitalBalance);

        // % de Cobro global: cobrados / (cobrados + activos en calle)
        $globalCollected = Financing::where('status', 'collected')->count();
        $globalActive    = Financing::whereIn('status', $outstandingStatuses)->count();
        $globalTotal     = $globalCollected + $globalActive;
        $this->snapshot['collection_pct']    = $globalTotal > 0
            ? round($globalCollected / $globalTotal * 100, 1)
            : 0;
        $this->snapshot['collected_count']   = $globalCollected;
        $this->snapshot['active_financings'] = $globalTotal;

        // ROI
        $this->snapshot['roi_period'] = $totalCapital > 0
            ? round(($this->snapshot['net_profit'] / $totalCapital) * 100, 2)
            : 0;

        $accumulatedProfit = (float) MonthlyClosing::sum('net_profit');
        if (! $this->snapshot['is_closed']) {
            $accumulatedProfit += $this->snapshot['net_profit'];
        }
        $this->snapshot['roi_accumulated'] = $totalCapital > 0
            ? round(($accumulatedProfit / $totalCapital) * 100, 2)
            : 0;

        // CxC: Cuentas por Cobrar. El cliente debe lo que ha recibido físicamente menos
        // lo cobrado (disbursed_amount - collected_amount). Los partially_disbursed no
        // tienen due_date, así que nunca aparecen como vencidos.
        $cxcTotal = (float) Financing::whereIn('status', $outstandingStatuses)
            ->selectRaw('SUM(disbursed_amount - collected_amount) as pending')->value('pending');
        $cxcCount = Financing::whereIn('status', $outstandingStatuses)->count();
        $cxcOverdueCount = Financing::whereIn('status', $outstandingStatuses)
            ->where('due_date', '<', Carbon::today())->count();
        $cxcOverdueTotal = (float) Financing::whereIn('status', $outstandingStatuses)
            ->where('due_date', '<', Carbon::today())
            ->selectRaw('SUM(disbursed_amount - collected_amount) as pending')->value('pending');

        $this->snapshot['cxc_total']         = $cxcTotal;
        $this->snapshot['cxc_count']         = $cxcCount;
        $this->snapshot['cxc_al_dia']        = $cxcTotal - $cxcOverdueTotal;
        $this->snapshot['cxc_al_dia_count']  = $cxcCount - $cxcOverdueCount;
        $this->snapshot['cxc_overdue']       = $cxcOverdueTotal;
        $this->snapshot['cxc_overdue_count'] = $cxcOverdueCount;

        // CxP: Cuentas por Pagar. Incluye solicitudes nuevas (solicited) y financiamientos
        // con partidas pendientes de desembolso (partially_disbursed). El total a desembolsar
        // es lo que falta del transfer_amount, no el transfer completo.
        $pendingDisbursementStatuses = ['solicited', 'partially_disbursed'];

        $this->snapshot['cxp_total']  = (float) Financing::whereIn('status', $pendingDisbursementStatuses)
            ->selectRaw('COALESCE(SUM(transfer_amount - disbursed_amount), 0) as pending')
            ->value('pending');
        $this->snapshot['cxp_count']  = Financing::whereIn('status', $pendingDisbursementStatuses)->count();
        $this->snapshot['cxp_amount'] = (float) Financing::where('status', 'solicited')->sum('amount');

        // Margen de solvencia: banco estimado vs ganancias comprometidas pendientes de pago
        $pendingEarnings = round(
            (float) Transaction::where('type', 'earning_distribution')->where('status', 'confirmed')->sum('amount')
            - (float) Transaction::whereIn('type', ['member_disbursement', 'earnings_to_capital'])->where('status', 'confirmed')->sum('amount'),
            2
        );
        $this->snapshot['pending_earnings']     = $pendingEarnings;
        $this->snapshot['solvency_margin']      = round($this->snapshot['estimated_bank'] - $pendingEarnings, 2);
        $this->snapshot['bank_covers_earnings'] = $this->snapshot['solvency_margin'] >= 0;
    }

    public function getChartData(): array
    {
        $closings = MonthlyClosing::orderBy('period')->get();

        $labels       = [];
        $commissions  = [];
        $expensesData = [];
        $netProfits   = [];

        foreach ($closings as $closing) {
            $labels[]       = Carbon::createFromFormat('Y-m', $closing->period)->translatedFormat('M Y');
            $commissions[]  = (float) $closing->total_commissions;
            $expensesData[] = (float) $closing->total_expenses;
            $netProfits[]   = (float) $closing->net_profit;
        }

        // Incluir período en curso si no está cerrado
        if ($this->snapshot && ! $this->snapshot['is_closed']) {
            $currentPeriod = $this->selectedPeriod ?? now()->format('Y-m');
            $alreadyIncluded = MonthlyClosing::where('period', $currentPeriod)->exists();

            if (! $alreadyIncluded) {
                $labels[]       = Carbon::createFromFormat('Y-m', $currentPeriod)->translatedFormat('M Y') . ' *';
                $commissions[]  = (float) $this->snapshot['total_commissions'];
                $expensesData[] = (float) $this->snapshot['total_expenses'];
                $netProfits[]   = (float) $this->snapshot['net_profit'];
            }
        }

        if (empty($labels)) {
            return ['labels' => [], 'datasets' => []];
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label'       => 'Comisiones',
                    'data'        => $commissions,
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'fill'        => false,
                    'tension'     => 0.3,
                ],
                [
                    'label'       => 'Gastos',
                    'data'        => $expensesData,
                    'borderColor' => '#ef4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'fill'        => false,
                    'tension'     => 0.3,
                ],
                [
                    'label'       => 'Ganancia Neta',
                    'data'        => $netProfits,
                    'borderColor' => '#22c55e',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'fill'        => true,
                    'tension'     => 0.3,
                ],
            ],
        ];
    }

    public function getFinancingsChartData(): array
    {
        $closings = MonthlyClosing::orderBy('period')->get();

        if ($closings->isEmpty()) {
            return ['labels' => [], 'collected' => [], 'disbursed' => []];
        }

        $collectedByMonth = Financing::where('status', 'collected')
            ->selectRaw('collection_period, COUNT(*) as count')
            ->groupBy('collection_period')
            ->pluck('count', 'collection_period');

        $disbursedByMonth = Financing::whereNotNull('issue_period')
            ->where('status', '!=', 'cancelled')
            ->selectRaw('issue_period, COUNT(*) as count')
            ->groupBy('issue_period')
            ->pluck('count', 'issue_period');

        $labels    = [];
        $collected = [];
        $disbursed = [];

        foreach ($closings as $closing) {
            $labels[]    = Carbon::createFromFormat('Y-m', $closing->period)->translatedFormat('M Y');
            $collected[] = (int) ($collectedByMonth[$closing->period] ?? 0);
            $disbursed[] = (int) ($disbursedByMonth[$closing->period] ?? 0);
        }

        return ['labels' => $labels, 'collected' => $collected, 'disbursed' => $disbursed];
    }

    public function getRoiChartData(): array
    {
        $closings = MonthlyClosing::orderBy('period')->get();

        $totalCapital = (float) FundMember::where('active', true)
            ->where(fn ($q) => $q->where('type', 'capital')
                ->orWhere(fn ($q2) => $q2->where('type', 'in_kind')->where('contribution', '>', 0))
            )
            ->sum('contribution');

        $labels = [];
        $rois   = [];

        foreach ($closings as $closing) {
            $labels[] = Carbon::createFromFormat('Y-m', $closing->period)->translatedFormat('M Y');
            $rois[]   = $totalCapital > 0
                ? round(((float) $closing->net_profit / $totalCapital) * 100, 2)
                : 0;
        }

        // Incluir período en curso
        if ($this->snapshot && ! $this->snapshot['is_closed']) {
            $currentPeriod = $this->selectedPeriod ?? now()->format('Y-m');
            if (! MonthlyClosing::where('period', $currentPeriod)->exists()) {
                $labels[] = Carbon::createFromFormat('Y-m', $currentPeriod)->translatedFormat('M Y') . ' *';
                $rois[]   = $this->snapshot['roi_period'] ?? 0;
            }
        }

        return ['labels' => $labels, 'rois' => $rois];
    }

    public function getCashflowChartData(): array
    {
        $period = $this->selectedPeriod ?? now()->format('Y-m');
        [$year, $month] = explode('-', $period);

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $endDate   = $startDate->copy()->endOfMonth();
        $today     = now()->endOfDay();
        $lastDate  = $endDate->lt($today) ? $endDate : $today;

        // Sólo tipos que mueven cash en el banco. Las distribuciones de ganancias
        // (earning_distribution) y capitalizaciones (earnings_to_capital) son
        // asientos contables internos — no afectan el saldo bancario.
        $cashflowOutTypes = ['disbursement', 'expense', 'member_disbursement'];

        // Saldo inicial: balance real del banco hoy (ledgers) menos los movimientos
        // de cash desde el inicio del período hasta ahora. Esto garantiza que la
        // trayectoria diaria reconstruya el saldo verdadero del banco.
        $bankNow = (float) CapitalAccount::instance()->balance
                 + (float) FundAccount::instance()->balance;

        $netSinceStart = (float) Transaction::where('type', 'collection')
                ->where('status', 'confirmed')
                ->where('transaction_date', '>=', $startDate)
                ->sum('amount')
            - (float) Transaction::whereIn('type', $cashflowOutTypes)
                ->where('status', 'confirmed')
                ->where('transaction_date', '>=', $startDate)
                ->sum('amount');

        $openingBalance = $bankNow - $netSinceStart;

        // Transacciones del período agrupadas por día (sólo cash)
        $transactions = Transaction::where('status', 'confirmed')
            ->whereIn('type', array_merge(['collection'], $cashflowOutTypes))
            ->whereBetween('transaction_date', [$startDate, $lastDate])
            ->selectRaw('DATE(transaction_date) as tx_date, type, SUM(amount) as total')
            ->groupBy('tx_date', 'type')
            ->orderBy('tx_date')
            ->get();

        $dailyFlows = [];
        foreach ($transactions as $tx) {
            $date = $tx->tx_date;
            if (! isset($dailyFlows[$date])) {
                $dailyFlows[$date] = ['inflows' => 0, 'outflows' => 0];
            }

            if ($tx->type === 'collection') {
                $dailyFlows[$date]['inflows'] += (float) $tx->total;
            } else {
                $dailyFlows[$date]['outflows'] += (float) $tx->total;
            }
        }

        $labels   = [];
        $balances = [];
        $inflows  = [];
        $outflows = [];

        $runningBalance = $openingBalance;
        $currentDate    = $startDate->copy();

        while ($currentDate->lte($lastDate)) {
            $dateStr = $currentDate->format('Y-m-d');
            $dayLabel = $currentDate->format('d M');

            $dayIn  = $dailyFlows[$dateStr]['inflows'] ?? 0;
            $dayOut = $dailyFlows[$dateStr]['outflows'] ?? 0;

            $runningBalance += $dayIn - $dayOut;

            $labels[]   = $dayLabel;
            $balances[] = round($runningBalance, 2);
            $inflows[]  = round($dayIn, 2);
            $outflows[] = round($dayOut * -1, 2);

            $currentDate->addDay();
        }

        $totalInflows  = array_sum($inflows);
        $totalOutflows = array_sum(array_map('abs', $outflows));
        $totalMoved    = $totalInflows + $totalOutflows;
        $avgBalance    = count($balances) > 0 ? array_sum($balances) / count($balances) : 0;

        return [
            'labels'         => $labels,
            'balances'       => $balances,
            'inflows'        => $inflows,
            'outflows'       => $outflows,
            'opening'        => round($openingBalance, 2),
            'total_inflows'  => round($totalInflows, 2),
            'total_outflows' => round($totalOutflows, 2),
            'total_moved'    => round($totalMoved, 2),
            'avg_balance'    => round($avgBalance, 2),
        ];
    }
}
