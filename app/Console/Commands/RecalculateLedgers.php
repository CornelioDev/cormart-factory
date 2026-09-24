<?php

namespace App\Console\Commands;

use App\Models\CapitalAccount;
use App\Models\FundAccount;
use App\Services\LedgerVerificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recalcula CapitalAccount.balance y FundAccount.balance desde cero usando la
 * semántica del modelo de claims-on-cash (capital + fund = cash bancario).
 *
 * Las distribuciones del cierre (earning_distribution) y la reserva de impuesto
 * dejan de ser debits del fondo. Los retiros de miembros y las capitalizaciones
 * sí debitan el fondo. El cash bancario debe quedar = CapitalAccount + FundAccount.
 */
class RecalculateLedgers extends Command
{
    protected $signature = 'app:recalculate-ledgers {--dry-run : Mostrar valores nuevos sin escribir}';

    protected $description = 'Recalcula los balances almacenados de CapitalAccount y FundAccount desde los eventos del sistema.';

    public function handle(): int
    {
        // Las fórmulas viven en LedgerVerificationService. Este comando ESCRIBE los
        // balances, así que una copia desactualizada aquí no reporta un descuadre:
        // lo crea. La versión anterior omitía el préstamo interno fondo↔capital y
        // las devoluciones de compañías, y habría borrado esos movimientos.
        $ledger  = app(LedgerVerificationService::class);
        $capital = $ledger->capitalBreakdown();
        $fund    = $ledger->fundBreakdown();

        $newCapital = $capital['expected'];
        $newFund    = $fund['expected'];

        $this->line('  Capital: ' . $capital['detail']);
        $this->line('  Fondo:   ' . $fund['detail']);

        $oldCapital = (float) CapitalAccount::instance()->balance;
        $oldFund    = (float) FundAccount::instance()->balance;

        $this->newLine();
        $this->info('Recálculo de ledgers (modelo claims-on-cash):');
        $this->table(
            ['Ledger', 'Anterior', 'Nuevo', 'Δ'],
            [
                ['CapitalAccount', $this->fmt($oldCapital), $this->fmt($newCapital), $this->fmt($newCapital - $oldCapital)],
                ['FundAccount',    $this->fmt($oldFund),    $this->fmt($newFund),    $this->fmt($newFund - $oldFund)],
                ['Bank (Cap+Fund)', $this->fmt($oldCapital + $oldFund), $this->fmt($newCapital + $newFund), $this->fmt(($newCapital + $newFund) - ($oldCapital + $oldFund))],
            ]
        );

        if ($this->option('dry-run')) {
            $this->warn('Dry-run: no se aplicaron cambios.');
            return self::SUCCESS;
        }

        if (! $this->confirm('¿Aplicar estos balances a la base de datos?', true)) {
            $this->warn('Cancelado.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($newCapital, $newFund) {
            CapitalAccount::instance()->update(['balance' => $newCapital]);
            FundAccount::instance()->update(['balance' => $newFund]);
        });

        $this->info('✓ Balances actualizados.');
        return self::SUCCESS;
    }

    private function fmt(float $v): string
    {
        return 'RD$ ' . number_format($v, 2, '.', ',');
    }
}
