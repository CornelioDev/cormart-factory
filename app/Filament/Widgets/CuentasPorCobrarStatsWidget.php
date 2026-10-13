<?php

namespace App\Filament\Widgets;

use App\Models\CompanyCredit;
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
        $user          = auth()->user();
        $isCompanyUser = $user->hasRole('company_user');
        $companyId     = $isCompanyUser ? $user->company_id : null;

        $base = fn () => Financing::whereIn('status', Financing::RECEIVABLE_STATUSES)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId));

        // Lo que el deudor debe es el monto de la factura menos lo cobrado, igual que
        // Financing::remainingBalance() y que el cobro que se le va a exigir. Antes se
        // usaba disbursed_amount, que omite la comisión: la pantalla mostraba menos de
        // lo adeudado y una compañía llegó a programar sus pagos contra esa cifra.
        $total        = (float) $base()->selectRaw('SUM(amount - collected_amount) as pending')->value('pending');
        $count        = $base()->count();
        $awaiting     = $base()->where('status', 'pending_payment')->count();
        $overdueCount = $base()->where('due_date', '<', Carbon::today())->count();
        $overdueTotal = (float) $base()->where('due_date', '<', Carbon::today())->selectRaw('SUM(amount - collected_amount) as pending')->value('pending');
        $alDia        = $total - $overdueTotal;
        $alDiaCount   = $count - $overdueCount;

        $collectedMonth = (float) Financing::where('status', 'collected')
            ->where('collection_period', now()->format('Y-m'))
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->sum('amount');

        // Efectivo que los deudores pagaron en cheque y sigue en manos de las
        // compañías: el financiamiento está cobrado, pero el fondo aún no tiene ese
        // dinero en el banco. Se liquida por transferencia o se aplica a un desembolso.
        $enManosDeCompanias = (float) CompanyCredit::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->sum('amount');

        return [
            Stat::make($isCompanyUser ? 'Total por Pagar' : 'Total por Cobrar', 'RD$ ' . number_format($total, 2, '.', ','))
                ->description(
                    $count . ' financiamiento' . ($count !== 1 ? 's' : '') . ' activos'
                    . ($awaiting > 0 ? ' · ' . $awaiting . ' con pago por confirmar' : '')
                )
                ->color('primary'),

            Stat::make('Al Día', 'RD$ ' . number_format($alDia, 2, '.', ','))
                ->description($alDiaCount . ' registro' . ($alDiaCount !== 1 ? 's' : ''))
                ->color('success'),

            Stat::make('Vencidos', 'RD$ ' . number_format($overdueTotal, 2, '.', ','))
                ->description($overdueCount . ' registro' . ($overdueCount !== 1 ? 's' : ''))
                ->color($overdueCount > 0 ? 'danger' : 'gray'),

            Stat::make(($isCompanyUser ? 'Pagado en ' : 'Cobrado en ') . now()->translatedFormat('M Y'), 'RD$ ' . number_format($collectedMonth, 2, '.', ','))
                ->description('mes actual')
                ->color('info'),

            Stat::make(
                $isCompanyUser ? 'En Mis Manos (cheques)' : 'En Manos de Compañías',
                'RD$ ' . number_format($enManosDeCompanias, 2, '.', ',')
            )
                ->description('cheques cobrados, pendientes de liquidar')
                ->url($isCompanyUser ? null : \App\Filament\Pages\SaldosCompaniasPage::getUrl())
                ->color($enManosDeCompanias > 0 ? 'warning' : 'gray'),
        ];
    }
}
