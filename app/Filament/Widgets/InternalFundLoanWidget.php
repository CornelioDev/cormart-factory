<?php

namespace App\Filament\Widgets;

use App\Models\CapitalAccount;
use App\Models\FundAccount;
use App\Models\Transaction;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InternalFundLoanWidget extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if (! $user->hasAnyRole(['super_admin', 'operator'])) {
            return false;
        }

        $loaned = (float) Transaction::where('type', 'fund_loan_to_capital')
            ->where('status', 'confirmed')->sum('amount');
        $repaid = (float) Transaction::where('type', 'capital_repayment_to_fund')
            ->where('status', 'confirmed')->sum('amount');

        return round($loaned - $repaid, 2) > 0;
    }

    protected function getStats(): array
    {
        $loaned = (float) Transaction::where('type', 'fund_loan_to_capital')
            ->where('status', 'confirmed')->sum('amount');
        $repaid = (float) Transaction::where('type', 'capital_repayment_to_fund')
            ->where('status', 'confirmed')->sum('amount');
        $outstanding = round($loaned - $repaid, 2);

        $capital = (float) CapitalAccount::instance()->balance;
        $fund    = (float) FundAccount::instance()->balance;

        return [
            Stat::make('Deuda Interna del Capital', 'RD$ ' . number_format($outstanding, 2, '.', ','))
                ->description('Cash del fondo prestado al capital')
                ->color('warning'),

            Stat::make('Capital Disponible', 'RD$ ' . number_format($capital, 2, '.', ','))
                ->description('CapitalAccount actual')
                ->color('primary'),

            Stat::make('Fondo Disponible', 'RD$ ' . number_format($fund, 2, '.', ','))
                ->description('FundAccount actual (ganancias retenidas)')
                ->color('success'),
        ];
    }
}
