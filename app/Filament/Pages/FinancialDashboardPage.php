<?php

namespace App\Filament\Pages;

use App\Models\Company;
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
    public array $financingsChartData = [];
    public array $companyChartData = [];

    public static function canAccess(): bool
    {
        return auth()->user()->can('page_FinancialDashboardPage');
    }

    public function mount(): void
    {
        $this->selectedPeriod = now()->format('Y-m');
        $this->loadSnapshot();
        $this->chartData = $this->getChartData();
        $this->financingsChartData = $this->getFinancingsChartData();
        $this->companyChartData = $this->getCompanyChartData();
    }

    public function updatedSelectedPeriod(): void
    {
        $this->loadSnapshot();
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

        // KPIs operativos
        [$year, $month] = explode('-', $this->selectedPeriod);

        $collectedCount = Financing::where('status', 'collected')
            ->where('collection_period', $this->selectedPeriod)
            ->count();

        $commissionsCollected = $this->snapshot['total_commissions'];

        $pendingCommissions = Financing::whereIn('status', ['disbursed', 'partially_collected'])
            ->sum('commission');
        $disbursedCount = Financing::whereIn('status', ['disbursed', 'partially_collected'])->count();

        $this->snapshot['projected_commissions'] = $commissionsCollected + $pendingCommissions;
        $this->snapshot['pending_in_street_count'] = $disbursedCount;

        $activeFinancings = $collectedCount + $disbursedCount;
        $this->snapshot['collection_pct'] = $activeFinancings > 0
            ? round($collectedCount / $activeFinancings * 100, 1)
            : 0;
        $this->snapshot['collected_count'] = $collectedCount;
        $this->snapshot['active_financings'] = $activeFinancings;

        $capitalInStreet = (float) Financing::whereIn('status', ['disbursed', 'partially_collected'])
            ->selectRaw('SUM(transfer_amount - collected_amount) as pending')
            ->value('pending');
        $this->snapshot['capital_in_street'] = $capitalInStreet;

        $totalCapital = (float) FundMember::where('type', 'capital')
            ->where('active', true)
            ->sum('contribution');
        $this->snapshot['capital_total'] = $totalCapital;
        $this->snapshot['capital_available'] = $totalCapital - $capitalInStreet;

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

        // Balance del fondo
        $this->snapshot['fund_balance'] = (float) FundAccount::instance()->balance;

        $collections = (float) Transaction::where('type', 'collection')
            ->where('status', 'confirmed')->sum('amount');
        $disbursements = (float) Transaction::where('type', 'disbursement')
            ->where('status', 'confirmed')->sum('amount');
        $expenses = (float) Transaction::where('type', 'expense')
            ->where('status', 'confirmed')->sum('amount');
        $memberDisbursements = (float) Transaction::where('type', 'member_disbursement')
            ->where('status', 'confirmed')->sum('amount');

        $this->snapshot['estimated_bank'] = $totalCapital + $collections - $disbursements - $expenses - $memberDisbursements;
    }

    public function getChartData(): array
    {
        $closings = MonthlyClosing::orderBy('period')->get();

        if ($closings->isEmpty()) {
            return ['labels' => [], 'datasets' => []];
        }

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

    public function getCompanyChartData(): array
    {
        $companyShare = Financing::where('status', '!=', 'cancelled')
            ->join('companies', 'companies.id', '=', 'financings.company_id')
            ->selectRaw('companies.name, SUM(financings.amount) as total')
            ->groupBy('companies.name')
            ->orderByDesc('total')
            ->get();

        if ($companyShare->isEmpty()) {
            return ['labels' => [], 'data' => []];
        }

        $colors = ['#f59e0b', '#3b82f6', '#10b981', '#8b5cf6', '#ef4444', '#06b6d4'];

        return [
            'labels' => $companyShare->pluck('name')->toArray(),
            'data'   => $companyShare->pluck('total')->map(fn ($v) => (float) $v)->toArray(),
            'colors' => array_slice($colors, 0, $companyShare->count()),
        ];
    }
}
