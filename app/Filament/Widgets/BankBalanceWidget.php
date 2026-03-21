<?php

namespace App\Filament\Widgets;

use App\Models\Financing;
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
        $totalCapital = (float) FundMember::where('type', 'capital')
            ->where('active', true)
            ->sum('contribution');

        // Capital desplegado: dinero prestado aún no cobrado
        $capitalDeployed = (float) Financing::whereIn('status', ['disbursed', 'partially_collected'])
            ->sum('transfer_amount');

        $fundBalance = (float) FundAccount::instance()->balance;

        // Ganancias distribuidas a miembros pero no desembolsadas aún
        $memberEarnings = (float) Transaction::where('type', 'earning_distribution')
            ->where('status', 'confirmed')
            ->sum('amount')
            - (float) Transaction::where('type', 'member_disbursement')
            ->where('status', 'confirmed')
            ->sum('amount');

        $estimatedBank = $totalCapital - $capitalDeployed + $fundBalance + $memberEarnings;

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
