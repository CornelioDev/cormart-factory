<?php

namespace App\Filament\Widgets;

use App\Models\Financing;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinancingPipelineWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    protected function getColumns(): int
    {
        return 4;
    }

    public static function canView(): bool
    {
        return true;
    }

    protected function getStats(): array
    {
        $user      = auth()->user();
        $companyId = $user->hasRole('company_user') ? $user->company_id : null;

        $base = fn () => $companyId
            ? Financing::where('company_id', $companyId)
            : Financing::query();

        $solicited        = $base()->where('status', 'solicited');
        $disbursed        = $base()->where('status', 'disbursed');
        $partialCollected = $base()->where('status', 'partially_collected');
        $period           = now()->format('Y-m');
        $collected        = $base()->where('status', 'collected')->where('collection_period', $period);

        return [
            Stat::make('Solicitados', $solicited->count())
                ->description('RD$ ' . number_format($solicited->sum('amount'), 2, '.', ','))
                ->color('warning')
                ->icon('heroicon-o-clock'),

            Stat::make('Desembolsados', $disbursed->count())
                ->description('RD$ ' . number_format($disbursed->sum('transfer_amount'), 2, '.', ',') . ' en calle')
                ->color('info')
                ->icon('heroicon-o-arrow-up-tray'),

            Stat::make('Abonados', $partialCollected->count())
                ->description('RD$ ' . number_format((float) $partialCollected->selectRaw('SUM(amount - collected_amount) as pending')->value('pending'), 2, '.', ',') . ' pendiente')
                ->color('primary')
                ->icon('heroicon-o-arrow-path'),

            Stat::make('Cobrados · ' . now()->translatedFormat('M Y'), $collected->count())
                ->description('RD$ ' . number_format($collected->sum('commission'), 2, '.', ',') . ' en comisiones')
                ->color('success')
                ->icon('heroicon-o-check-circle'),
        ];
    }
}
