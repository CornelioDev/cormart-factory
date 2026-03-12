<?php

namespace App\Filament\Widgets;

use App\Models\Financing;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CuentasPorPagarStatsWidget extends StatsOverviewWidget
{
    protected static bool $isLazy       = false;
    protected static bool $isDiscovered = false;

    protected function getStats(): array
    {
        $totalTransfer = (float) Financing::where('status', 'solicited')->sum('transfer_amount');
        $totalAmount   = (float) Financing::where('status', 'solicited')->sum('amount');
        $count         = Financing::where('status', 'solicited')->count();
        $avgDays       = (float) (Financing::where('status', 'solicited')
            ->selectRaw('AVG(DATEDIFF(NOW(), request_date)) as avg_days')
            ->value('avg_days') ?? 0);

        return [
            Stat::make('Total por Desembolsar', 'RD$ ' . number_format($totalTransfer, 2, '.', ','))
                ->description('neto después de comisiones')
                ->color('primary'),

            Stat::make('Monto Total Solicitado', 'RD$ ' . number_format($totalAmount, 2, '.', ','))
                ->description('monto bruto')
                ->color('gray'),

            Stat::make('Solicitudes Pendientes', (string) $count)
                ->description('financiamiento' . ($count !== 1 ? 's' : '') . ' en cola')
                ->color($count > 0 ? 'warning' : 'gray'),

            Stat::make('Antigüedad Promedio', number_format($avgDays, 0) . ' días')
                ->description('desde la solicitud')
                ->color($avgDays > 3 ? 'warning' : 'gray'),
        ];
    }
}
