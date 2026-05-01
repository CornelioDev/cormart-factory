<?php

namespace App\Console\Commands;

use App\Models\CapitalAccount;
use App\Models\Financing;
use App\Models\FundAccount;
use App\Models\FundMember;
use App\Models\Transaction;
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
        $activeCapital = (float) FundMember::where('active', true)
            ->where(fn ($q) => $q->where('type', 'capital')
                ->orWhere(fn ($q2) => $q2->where('type', 'in_kind')->where('contribution', '>', 0))
            )
            ->sum('contribution');

        $financingsActive = Financing::whereNotIn('status', ['solicited', 'cancelled']);
        $disbursedPhysical  = (float) (clone $financingsActive)->sum('disbursed_amount');
        $commissionRetained = (float) (clone $financingsActive)->sum('commission');
        $collectedToCapital = (float) (clone $financingsActive)->sum('collected_amount');
        $lateFeeCollected   = (float) Financing::sum('late_fee_amount');

        $confirmed = fn (string $type) => (float) Transaction::where('type', $type)
            ->where('status', 'confirmed')->sum('amount');

        $expenseTxn         = $confirmed('expense');
        $memberDisbursement = $confirmed('member_disbursement');
        $earningsToCapital  = $confirmed('earnings_to_capital');

        $newCapital = round(
            $activeCapital - $disbursedPhysical - $commissionRetained + $collectedToCapital,
            2
        );

        $newFund = round(
            $commissionRetained + $lateFeeCollected
            - $expenseTxn - $memberDisbursement - $earningsToCapital,
            2
        );

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
