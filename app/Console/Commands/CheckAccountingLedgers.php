<?php

namespace App\Console\Commands;

use App\Services\LedgerVerificationService;
use Illuminate\Console\Command;

class CheckAccountingLedgers extends Command
{
    protected $signature = 'app:check-accounting-ledgers';

    protected $description = 'Verifica los ledgers contables y notifica a super_admin si hay discrepancias';

    public function handle(): int
    {
        $service = new LedgerVerificationService();
        $failed = $service->runChecks();

        if (empty($failed)) {
            $this->info('Todos los ledgers están en orden.');

            return self::SUCCESS;
        }

        $this->warn('Se detectaron ' . count($failed) . ' error(es) contable(s):');

        foreach ($failed as $check) {
            $this->line("  - {$check['name']}: diferencia RD$ " . number_format($check['diff'], 2, '.', ','));
        }

        $service->verifyAndNotifyForced();

        $this->info('Notificación enviada a super_admin.');

        return self::FAILURE;
    }
}
