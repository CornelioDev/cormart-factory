<?php

namespace App\Console\Commands;

use App\Models\Financing;
use App\Models\Parameter;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendFinancingAlerts extends Command
{
    protected $signature = 'app:send-financing-alerts';

    protected $description = 'Envía alertas por email de financiamientos próximos a vencer y vencidos';

    public function handle(): int
    {
        $dueAlertDays = (int) Parameter::where('key', 'due_alert_days')->value('value') ?: 5;

        $today    = now()->startOfDay();
        $alertEnd = $today->copy()->addDays($dueAlertDays);

        // Financiamientos activos con fecha de vencimiento
        $financings = Financing::whereIn('status', ['disbursed', 'partially_collected'])
            ->whereNotNull('due_date')
            ->with(['company', 'client'])
            ->get();

        if ($financings->isEmpty()) {
            $this->info('No hay financiamientos activos con fecha de vencimiento.');

            return self::SUCCESS;
        }

        // Separar: próximos a vencer (hoy <= due_date <= hoy + alert_days) y vencidos (due_date < hoy)
        $approaching = $financings->filter(
            fn (Financing $f) => $f->due_date->startOfDay()->gte($today)
                && $f->due_date->startOfDay()->lte($alertEnd)
        );

        $overdue = $financings->filter(
            fn (Financing $f) => $f->due_date->startOfDay()->lt($today)
        );

        if ($approaching->isEmpty() && $overdue->isEmpty()) {
            $this->info('No hay financiamientos próximos a vencer ni vencidos.');

            return self::SUCCESS;
        }

        $this->info("Próximos a vencer: {$approaching->count()} | Vencidos: {$overdue->count()}");

        (new NotificationService())->sendDueDateAlerts($approaching, $overdue);

        $this->info('Alertas enviadas correctamente.');

        return self::SUCCESS;
    }
}
