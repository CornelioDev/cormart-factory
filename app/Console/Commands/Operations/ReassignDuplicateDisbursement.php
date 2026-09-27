<?php

namespace App\Console\Commands\Operations;

use App\Models\Financing;
use App\Models\MonthlyClosing;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Traslada el desembolso de un financiamiento duplicado a otro financiamiento
 * solicitado de la misma compañía, sin que el efectivo se mueva del banco.
 *
 * El caso: la compañía solicitó por error un financiamiento que ya estaba
 * cubierto, el fondo lo desembolsó, y el efectivo está en manos de la compañía
 * sin necesitarse. Cancelar no es posible desde la UI (solo se cancela en
 * `solicited`) y por buena razón: cancelar sin más descuadraría el ledger.
 *
 * Por qué no sirve el libro de cuentas por cobrar a compañías aquí:
 * la invariante del capital contabiliza el efectivo que salió del banco vía
 * `disbursed_amount` de los financiamientos ACTIVOS — así es como el
 * sobre-desembolso de FN000130 quedó cuadrado sin mover ledgers. Si se cancela
 * el duplicado, su `disbursed_amount` sale de la suma y el efectivo pierde su
 * asiento contable: `company_receivable` no figura en la fórmula, y
 * `company_repayment` suma como si el efectivo hubiera vuelto al banco. El par
 * receivable/repayment es para cuando el dinero regresa de verdad; acá no
 * regresa, se redirige.
 *
 * Lo que hace este comando: el desembolso deja de pertenecer al duplicado y
 * pasa a pertenecer al financiamiento destino. El efectivo nunca se mueve, así
 * que el banco total no cambia. El único ajuste de ledgers es la diferencia
 * entre las dos comisiones:
 *
 *   capital  += comisión(duplicado) − comisión(destino)
 *   fondo    += comisión(destino)   − comisión(duplicado)
 *
 * Si ambas comisiones son iguales, no hay ajuste alguno.
 *
 * Restricción que no se puede saltar: el destino debe tener `transfer_amount`
 * mayor o igual al efectivo desembolsado. Si sobrara efectivo, ese remanente no
 * tendría dónde vivir en la invariante y la Reconciliación acusaría un descuadre
 * hasta que la compañía lo devuelva.
 *
 * El impuesto del desembolso original (gasto a DGII) no se revierte: es efectivo
 * que ya salió al fisco y se queda como gasto del período.
 */
class ReassignDuplicateDisbursement extends OperationCommand
{
    protected $signature = 'ops:reassign-duplicate-disbursement
        {--from= : Código del financiamiento duplicado, ej. FN000131}
        {--to= : Código del financiamiento solicitado que recibirá el desembolso}
        {--reason= : Motivo de la cancelación del duplicado}';

    protected $description = 'Traslada el desembolso de un financiamiento duplicado a otro solicitado de la misma compañía, sin mover efectivo.';

    protected function perform(): int
    {
        $fromCode = (string) $this->option('from');
        $toCode   = (string) $this->option('to');

        if ($fromCode === '' || $toCode === '') {
            throw new RuntimeException('Se requieren --from y --to. Ej: --from=FN000131 --to=FN000132');
        }

        $this->requireBalancedLedgers();

        [$from, $to] = $this->resolveFinancings($fromCode, $toCode);

        $cash         = round((float) $from->disbursed_amount, 2);
        $commissionA  = round((float) $from->commission, 2);
        $commissionB  = round((float) $to->commission, 2);
        $capitalDelta = round($commissionA - $commissionB, 2);
        $period       = (string) $from->issue_period;
        $fullyCovered = $cash + 0.001 >= (float) $to->transfer_amount;

        // La fecha real en que el efectivo salió del banco, no la de la corrección:
        // debe seguir siendo coherente con el issue_period que se hereda.
        $disbursedAt = $from->disbursed_at;

        $this->renderPlan($from, $to, $cash, $commissionA, $commissionB, $capitalDelta, $fullyCovered);

        if ($this->isDryRun()) {
            $this->newLine();
            $this->warn('DRY RUN — no se escribió nada.');

            return self::SUCCESS;
        }

        if (! $this->confirm('¿Aplicar el traslado?', false)) {
            $this->line('Cancelado por el operador.');

            return self::SUCCESS;
        }

        $reason = (string) ($this->option('reason')
            ?: "Solicitud duplicada. El desembolso de {$this->money($cash)} se trasladó a {$to->code}.");
        $reason .= ' — Autorizado por ' . $this->actor()->email . '.';

        DB::transaction(function () use ($from, $to, $cash, $capitalDelta, $period, $disbursedAt, $fullyCovered, $reason) {
            // El duplicado deja de existir a efectos contables. Queda `cancelled`,
            // que ya está excluido de todas las sumas de la invariante.
            $from->update([
                'status'              => 'cancelled',
                'cancellation_reason' => $reason,
                'disbursed_amount'    => 0,
                'commission'          => 0,
                'issue_period'        => null,
                'disbursed_at'        => null,
                'confirmed_at'        => null,
                'confirmed_by'        => null,
                'due_date'            => null,
            ]);

            // El destino recibe el efectivo que ya está en manos de la compañía.
            // Si no lo cubre por completo queda `partially_disbursed` y el resto
            // se desembolsa después como una partida normal desde la UI.
            $to->update([
                'status'           => $fullyCovered ? 'disbursed' : 'partially_disbursed',
                'disbursed_amount' => $fullyCovered ? (float) $to->transfer_amount : $cash,
                'issue_period'     => $period,
                'disbursed_at'     => $disbursedAt,
            ]);

            // El rastro del dinero sigue al financiamiento: la transacción de
            // desembolso original pasa a colgar del destino.
            $this->repointDisbursements($from, $to, $reason);

            // Único ajuste de ledgers: la diferencia de comisiones. El efectivo
            // no se movió, así que capital + fondo queda igual.
            $this->adjustCommissionDelta($capitalDelta);

            $this->assertLedgersBalanced();
        });

        $this->logOperation("{$from->code} cancelado, desembolso trasladado a {$to->code}", [
            'from'          => $from->code,
            'to'            => $to->code,
            'cash'          => $cash,
            'capital_delta' => $capitalDelta,
            'period'        => $period,
        ]);

        $this->newLine();
        $this->info("Listo. {$from->code} quedó cancelado y {$to->code} recibió el desembolso de {$this->money($cash)}.");
        $this->line('Los ledgers quedaron cuadrados.');

        return self::SUCCESS;
    }

    /**
     * Carga y valida ambos financiamientos.
     *
     * @return array{0: Financing, 1: Financing}
     */
    private function resolveFinancings(string $fromCode, string $toCode): array
    {
        $from = Financing::where('code', $fromCode)->first();
        $to   = Financing::where('code', $toCode)->first();

        if (! $from) {
            throw new RuntimeException("No existe el financiamiento {$fromCode}.");
        }

        if (! $to) {
            throw new RuntimeException("No existe el financiamiento {$toCode}.");
        }

        if ($from->id === $to->id) {
            throw new RuntimeException('El origen y el destino son el mismo financiamiento.');
        }

        if (! in_array($from->status, ['partially_disbursed', 'disbursed'], true)) {
            throw new RuntimeException(
                "{$from->code} está en estado '{$from->status}'. Solo se puede trasladar el desembolso de "
                . 'un financiamiento desembolsado y sin cobros.'
            );
        }

        if (round((float) $from->collected_amount, 2) > 0) {
            throw new RuntimeException(
                "{$from->code} ya tiene cobros aplicados ({$this->money((float) $from->collected_amount)}). "
                . 'Revierta los cobros antes de trasladar el desembolso.'
            );
        }

        if (round((float) $from->late_fee_amount, 2) > 0 || round((float) $from->late_fee_pending, 2) > 0) {
            throw new RuntimeException("{$from->code} tiene mora registrada. Requiere revisión manual.");
        }

        if (round((float) $from->disbursed_amount, 2) <= 0) {
            throw new RuntimeException("{$from->code} no tiene monto desembolsado que trasladar.");
        }

        if (! $from->issue_period) {
            throw new RuntimeException("{$from->code} no tiene issue_period. Requiere revisión manual.");
        }

        // Un período cerrado ya repartió esa comisión entre los miembros. Reescribirlo
        // invalidaría el cierre; la reversión tendría que ir como gasto del mes actual.
        if (MonthlyClosing::where('period', $from->issue_period)->exists()) {
            throw new RuntimeException(
                "El período {$from->issue_period} ya tiene un cierre ejecutado. La comisión de {$from->code} "
                . 'ya se distribuyó a los miembros y no se puede reescribir.'
            );
        }

        if ($to->status !== 'solicited') {
            throw new RuntimeException(
                "{$to->code} está en estado '{$to->status}'. El destino debe estar en 'solicited'."
            );
        }

        if ((int) $from->company_id !== (int) $to->company_id) {
            throw new RuntimeException(
                'Ambos financiamientos deben ser de la misma compañía: el efectivo ya está en manos de '
                . 'la compañía de ' . $from->code . '.'
            );
        }

        // Si sobrara efectivo, el remanente no tendría asiento en la invariante
        // y la Reconciliación acusaría un descuadre por ese monto.
        $cash = round((float) $from->disbursed_amount, 2);
        if ($cash > (float) $to->transfer_amount + 0.001) {
            $candidates = Financing::where('company_id', $from->company_id)
                ->where('status', 'solicited')
                ->where('transfer_amount', '>=', $cash)
                ->pluck('code')
                ->implode(', ');

            throw new RuntimeException(
                'El efectivo desembolsado (' . $this->money($cash) . ') supera el monto a transferir de '
                . $to->code . ' (' . $this->money((float) $to->transfer_amount) . '). '
                . 'El remanente quedaría sin asiento contable. '
                . ($candidates !== ''
                    ? "Financiamientos que sí lo cubren: {$candidates}."
                    : 'Ningún financiamiento solicitado de esta compañía lo cubre; la compañía debe devolver el efectivo.')
            );
        }

        return [$from, $to];
    }

    /**
     * Reapunta al destino las transacciones de desembolso del duplicado.
     */
    private function repointDisbursements(Financing $from, Financing $to, string $reason): void
    {
        $disbursements = $from->transactions()
            ->where('type', 'disbursement')
            ->where('status', 'confirmed')
            ->get();

        foreach ($disbursements as $disbursement) {
            $disbursement->financings()->detach($from->id);
            $disbursement->financings()->syncWithoutDetaching([$to->id]);

            $note = trim((string) $disbursement->notes);
            $disbursement->update([
                'notes' => trim($note . " | Reasignado de {$from->code} a {$to->code}: {$reason}"),
            ]);
        }
    }

    /**
     * Mueve entre capital y fondo la diferencia entre las dos comisiones.
     * El banco total no cambia: lo que sale de uno entra al otro.
     */
    private function adjustCommissionDelta(float $capitalDelta): void
    {
        if (abs($capitalDelta) < 0.01) {
            return;
        }

        $capital = new \App\Services\CapitalAccountService();
        $fund    = new \App\Services\FundAccountService();

        if ($capitalDelta > 0) {
            $capital->credit($capitalDelta);
            $fund->debit($capitalDelta);
        } else {
            $capital->debit(abs($capitalDelta));
            $fund->credit(abs($capitalDelta));
        }
    }

    private function renderPlan(
        Financing $from,
        Financing $to,
        float $cash,
        float $commissionA,
        float $commissionB,
        float $capitalDelta,
        bool $fullyCovered
    ): void {
        $this->newLine();
        $this->info('Plan de traslado');
        $this->table(['Concepto', 'Valor'], [
            ['Duplicado a cancelar', $from->code . ' (' . $from->status . ')'],
            ['Destino', $to->code . ' (' . $to->status . ')'],
            ['Compañía', $from->company?->name ?? $from->company_id],
            ['Período', (string) $from->issue_period],
            ['Efectivo en manos de la compañía', $this->money($cash)],
            ['Monto a transferir del destino', $this->money((float) $to->transfer_amount)],
            ['Estado final del destino', $fullyCovered ? 'disbursed' : 'partially_disbursed'],
            ['Comisión liberada del duplicado', $this->money($commissionA)],
            ['Comisión retenida del destino', $this->money($commissionB)],
            ['Ajuste a capital', $this->money($capitalDelta)],
            ['Ajuste al fondo', $this->money(-$capitalDelta)],
            ['Movimiento real de banco', $this->money(0)],
        ]);

        if (! $fullyCovered) {
            $pending = round((float) $to->transfer_amount - $cash, 2);
            $this->warn(
                $to->code . ' quedará en partially_disbursed. Falta desembolsar ' . $this->money($pending)
                . ', que se registra como una partida normal desde la UI.'
            );
        }

        $this->line('El impuesto del desembolso original no se revierte: ya salió al fisco.');
    }
}
