<?php

namespace App\Filament\Widgets;

use App\Models\Financing;
use App\Models\FundMember;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CapitalSummaryWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['super_admin', 'operator', 'member']);
    }

    protected function getStats(): array
    {
        $currentPeriod = now()->format('Y-m');

        $totalCapital = FundMember::where('type', 'capital')
            ->where('active', true)
            ->sum('contribution');

        // Capital "en calle" = dinero desembolsado pendiente de cobro (neto de abonos)
        $capitalInStreet = (float) Financing::whereIn('status', ['disbursed', 'partially_collected'])
            ->selectRaw('SUM(transfer_amount - collected_amount) as pending')
            ->value('pending');

        $availableCapital = $totalCapital - $capitalInStreet;

        $commissionsThisMonth = Financing::where('status', 'collected')
            ->where('collection_period', $currentPeriod)
            ->sum('commission');

        $collectedThisMonth = Financing::where('status', 'collected')
            ->where('collection_period', $currentPeriod)
            ->count();

        $pendingCommissions = Financing::whereIn('status', ['disbursed', 'partially_collected'])
            ->sum('commission');

        $disbursedCount = Financing::whereIn('status', ['disbursed', 'partially_collected'])->count();

        $projectedCommissions = $commissionsThisMonth + $pendingCommissions;

        $activeFinancings = $collectedThisMonth + $disbursedCount;
        $collectionPct    = $activeFinancings > 0
            ? round($collectedThisMonth / $activeFinancings * 100, 1)
            : 0;

        return [
            Stat::make('Capital Total', 'RD$ ' . number_format($totalCapital, 2, '.', ','))
                ->description('Aportación activa del fondo')
                ->color('primary'),

            Stat::make('Capital en Calle', 'RD$ ' . number_format($capitalInStreet, 2, '.', ','))
                ->description('Financiamientos desembolsados pendientes')
                ->color('warning'),

            Stat::make('Capital Disponible', 'RD$ ' . number_format($availableCapital, 2, '.', ','))
                ->description('Listo para nuevos financiamientos')
                ->color('success'),

            Stat::make('Comisiones del Mes', 'RD$ ' . number_format($commissionsThisMonth, 2, '.', ','))
                ->description($collectedThisMonth . ' cobrados · ' . now()->translatedFormat('F Y'))
                ->color('primary'),

            Stat::make('Proyección de Comisiones', 'RD$ ' . number_format($projectedCommissions, 2, '.', ','))
                ->description('Cobrado + ' . $disbursedCount . ' pendientes en calle')
                ->color('info')
                ->icon('heroicon-o-chart-bar'),

            Stat::make('% de Cobro', $collectionPct . '%')
                ->description($collectedThisMonth . ' cobrados de ' . $activeFinancings . ' activos')
                ->color($collectionPct >= 75 ? 'success' : ($collectionPct >= 40 ? 'warning' : 'danger'))
                ->icon('heroicon-o-check-badge'),
        ];
    }
}
