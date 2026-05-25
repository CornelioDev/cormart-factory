<?php

namespace App\Console\Commands;

use App\Models\ClosingDistribution;
use App\Models\FundMember;
use App\Models\MonthlyClosing;
use App\Models\Transaction;
use App\Services\CapitalAccountService;
use App\Services\FundAccountService;
use App\Services\FundMemberService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixAprilTwentyTwentySixClosing extends Command
{
    protected $signature = 'factory:fix-april-2026-closing
                            {--dry-run : Muestra los cambios sin aplicarlos}';

    protected $description = 'Corrige el cierre 2026-04 afectado por el bug de double-dip in_kind (José Sr. recibió 338.58 que correspondían a Flia).';

    private const PERIOD = '2026-04';
    private const DELTA = 338.58;
    private const ADJUSTMENT_NOTE = 'Ajuste cierre 2026-04 — corrección bug double-dip in_kind';

    public function handle(): int
    {
        $closing = MonthlyClosing::where('period', self::PERIOD)->first();
        if (! $closing) {
            $this->error('No existe cierre para el período ' . self::PERIOD . '.');
            return self::FAILURE;
        }

        $capital = ClosingDistribution::where('monthly_closing_id', $closing->id)
            ->whereHas('fundMember', fn ($q) => $q->where('type', 'capital'))
            ->first();
        $inKind = ClosingDistribution::where('monthly_closing_id', $closing->id)
            ->whereHas('fundMember', fn ($q) => $q->where('type', 'in_kind'))
            ->first();

        if (! $capital || ! $inKind) {
            $this->error('No se encontraron las distribuciones esperadas (capital + in_kind).');
            return self::FAILURE;
        }

        if ((float) $inKind->proportional_amount === (float) round($closing->in_kind_payment, 2)) {
            $this->info('El cierre ya fue corregido. Nada que hacer.');
            return self::SUCCESS;
        }

        $expectedInKindProp = round((float) $closing->in_kind_payment, 2);
        $expectedCapitalProp = round((float) $closing->available_for_capital, 2);
        $delta = round((float) $inKind->proportional_amount - $expectedInKindProp, 2);

        if (abs($delta - self::DELTA) > 0.01) {
            $this->error("Delta calculada ({$delta}) no coincide con la esperada (" . self::DELTA . '). Abortando por seguridad.');
            return self::FAILURE;
        }

        $capitalMember = $capital->fundMember;
        $inKindMember = $inKind->fundMember;

        $this->table(['Concepto', 'Antes', 'Después'], [
            ["Flia. proportional",   number_format($capital->proportional_amount, 2), number_format($expectedCapitalProp, 2)],
            ["Flia. total",          number_format($capital->total_amount, 2),        number_format($capital->fixed_amount + $expectedCapitalProp, 2)],
            ["José Sr. proportional", number_format($inKind->proportional_amount, 2), number_format($expectedInKindProp, 2)],
            ["José Sr. total",       number_format($inKind->total_amount, 2),         number_format($inKind->fixed_amount + $expectedInKindProp, 2)],
            ["José Sr. contribution", number_format($inKindMember->contribution, 2),  number_format($inKindMember->contribution - $delta, 2)],
        ]);

        if ($this->option('dry-run')) {
            $this->warn('Dry-run: no se aplicaron cambios.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($closing, $capital, $inKind, $capitalMember, $inKindMember, $expectedInKindProp, $expectedCapitalProp, $delta) {
            $capital->update([
                'proportional_amount' => $expectedCapitalProp,
                'total_amount'        => round($capital->fixed_amount + $expectedCapitalProp, 2),
            ]);

            $inKind->update([
                'proportional_amount' => $expectedInKindProp,
                'total_amount'        => round($inKind->fixed_amount + $expectedInKindProp, 2),
            ]);

            // Tx earning_distribution del in_kind: reducir monto al correcto.
            $inKindEarningTx = Transaction::where('type', 'earning_distribution')
                ->where('fund_member_id', $inKindMember->id)
                ->where('notes', 'like', "%{$closing->period}%")
                ->firstOrFail();

            $inKindEarningTx->update([
                'amount' => round($inKindEarningTx->amount - $delta, 2),
                'notes'  => $inKindEarningTx->notes . ' | ' . self::ADJUSTMENT_NOTE,
            ]);

            // Crear earning_distribution complementaria para el miembro de capital.
            Transaction::create([
                'type'             => 'earning_distribution',
                'status'           => 'confirmed',
                'amount'           => $delta,
                'transaction_date' => now()->toDateString(),
                'fund_member_id'   => $capitalMember->id,
                'notes'            => self::ADJUSTMENT_NOTE,
                'registered_by'    => 1,
                'confirmed_by'     => 1,
                'confirmed_at'     => now(),
            ]);

            // Tx earnings_to_capital del in_kind: reducir capitalización al monto correcto
            // y revertir el exceso en ledgers (capital → fondo) y en contribution del miembro.
            $inKindCapitalizationTx = Transaction::where('type', 'earnings_to_capital')
                ->where('fund_member_id', $inKindMember->id)
                ->orderByDesc('id')
                ->firstOrFail();

            $inKindCapitalizationTx->update([
                'amount' => round($inKindCapitalizationTx->amount - $delta, 2),
                'notes'  => ($inKindCapitalizationTx->notes ?? '') . ' | ' . self::ADJUSTMENT_NOTE,
            ]);

            $inKindMember->decrement('contribution', $delta);

            (new CapitalAccountService())->debit($delta);
            (new FundAccountService())->credit($delta);

            (new FundMemberService())->recalculateAllPercentages();

            // verification_diff del closing queda en 0 (totales agregados no cambiaron).
            $closing->update(['verification_diff' => 0]);
        });

        $this->info('Cierre 2026-04 corregido exitosamente.');
        $this->line('Flia tiene un saldo de RD$ ' . number_format(self::DELTA, 2) . ' disponible para retirar.');

        return self::SUCCESS;
    }
}
