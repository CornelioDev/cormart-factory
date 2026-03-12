<?php

namespace App\Filament\Widgets;

use App\Models\Financing;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CuentasPorCobrarStatsWidget extends StatsOverviewWidget
{
    protected static bool $isLazy       = false;
    protected static bool $isDiscovered = false;

    protected function getStats(): array
    {
        $user      = auth()->user();
        $companyId = $user->hasRole('company_user') ? $user->company_id : null;

        $base = fn () => Financing::where('status', 'disbursed')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId));

        $total        = (float) $base()->sum('transfer_amount');
        $count        = $base()->count();
        $overdueCount = $base()->where('due_date', '<', Carbon::today())->count();
        $overdueTotal = (float) $base()->where('due_date', '<', Carbon::today())->sum('transfer_amount');
        $alDia        = $total - $overdueTotal;
        $alDiaCount   = $count - $overdueCount;

        $collectedMonth = (float) Financing::where('status', 'collected')
            ->where('collection_period', now()->format('Y-m'))
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->sum('amount');

        return [
            Stat::make('Total por Cobrar', 'RD$ ' . number_format($total, 2, '.', ','))
                ->description($count . ' financiamiento' . ($count !== 1 ? 's' : '') . ' activos')
                ->color('primary'),

            Stat::make('Al Día', 'RD$ ' . number_format($alDia, 2, '.', ','))
                ->description($alDiaCount . ' registro' . ($alDiaCount !== 1 ? 's' : ''))
                ->color('success'),

            Stat::make('Vencidos', 'RD$ ' . number_format($overdueTotal, 2, '.', ','))
                ->description($overdueCount . ' registro' . ($overdueCount !== 1 ? 's' : ''))
                ->color($overdueCount > 0 ? 'danger' : 'gray'),

            Stat::make('Cobrado en ' . now()->translatedFormat('M Y'), 'RD$ ' . number_format($collectedMonth, 2, '.', ','))
                ->description('mes actual')
                ->color('info'),
        ];
    }
}
