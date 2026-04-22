<?php

namespace App\Filament\Widgets;

use App\Models\Financing;
use App\Models\Transaction;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CuentasPorPagarStatsWidget extends StatsOverviewWidget
{
    protected static bool $isLazy       = false;
    protected static bool $isDiscovered = false;

    protected function getStats(): array
    {
        $pendingStatuses = ['solicited', 'partially_disbursed'];

        $totalTransfer = (float) Financing::whereIn('status', $pendingStatuses)
            ->selectRaw('COALESCE(SUM(transfer_amount - disbursed_amount), 0) as pending')
            ->value('pending');
        $totalAmount   = (float) Financing::where('status', 'solicited')->sum('amount');
        $count         = Financing::whereIn('status', $pendingStatuses)->count();
        $avgDays       = (float) (Financing::whereIn('status', $pendingStatuses)
            ->selectRaw('AVG(DATEDIFF(NOW(), request_date)) as avg_days')
            ->value('avg_days') ?? 0);

        return [
            Stat::make('Total por Desembolsar', 'RD$ ' . number_format($totalTransfer, 2, '.', ','))
                ->description('neto después de comisiones')
                ->color('primary'),

            Stat::make('Monto Total Solicitado', 'RD$ ' . number_format($totalAmount, 2, '.', ','))
                ->description('monto bruto')
                ->color('gray'),

            Stat::make('Desembolsos del Mes', 'RD$ ' . number_format(
                    (float) Transaction::where('type', 'disbursement')
                        ->where('status', 'confirmed')
                        ->whereMonth('transaction_date', now()->month)
                        ->whereYear('transaction_date', now()->year)
                        ->sum('amount'), 2, '.', ','
                ))
                ->description(
                    Transaction::where('type', 'disbursement')
                        ->where('status', 'confirmed')
                        ->whereMonth('transaction_date', now()->month)
                        ->whereYear('transaction_date', now()->year)
                        ->count() . ' transacción(es) este mes'
                )
                ->color('info'),

            Stat::make('Antigüedad Promedio', number_format($avgDays, 0) . ' días')
                ->description('desde la solicitud')
                ->color($avgDays > 3 ? 'warning' : 'gray'),
        ];
    }
}
