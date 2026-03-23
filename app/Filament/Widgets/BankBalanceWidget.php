<?php

namespace App\Filament\Widgets;

use App\Models\FundAccount;
use App\Models\FundMember;
use App\Models\Transaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BankBalanceWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    public static function canView(): bool
    {
        return auth()->user()->hasRole('super_admin');
    }

    protected function getStats(): array
    {
        $fundBalance = (float) FundAccount::instance()->balance;

        $totalCapital = (float) FundMember::where('type', 'capital')
            ->where('active', true)
            ->sum('contribution');

        // Flujo de caja real: solo transacciones confirmadas
        $collections = (float) Transaction::where('type', 'collection')
            ->where('status', 'confirmed')->sum('amount');

        $disbursements = (float) Transaction::where('type', 'disbursement')
            ->where('status', 'confirmed')->sum('amount');

        $expenses = (float) Transaction::where('type', 'expense')
            ->where('status', 'confirmed')->sum('amount');

        $memberDisbursements = (float) Transaction::where('type', 'member_disbursement')
            ->where('status', 'confirmed')->sum('amount');

        $estimatedBank = $totalCapital + $collections - $disbursements - $expenses - $memberDisbursements;

        return [
            Stat::make('Ganancias del Fondo', 'RD$ ' . number_format($fundBalance, 2, '.', ','))
                ->description('Comisiones − gastos − distribuciones')
                ->color($fundBalance >= 0 ? 'success' : 'danger')
                ->icon('heroicon-o-chart-bar'),

            Stat::make('Saldo Estimado Banco', 'RD$ ' . number_format($estimatedBank, 2, '.', ','))
                ->description('Total esperado en cuenta bancaria')
                ->color('primary')
                ->icon('heroicon-o-building-library'),
        ];
    }
}
