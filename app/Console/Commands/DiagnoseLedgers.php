<?php

namespace App\Console\Commands;

use App\Models\CapitalAccount;
use App\Models\ClosingDistribution;
use App\Models\ClosingParametersSnapshot;
use App\Models\Financing;
use App\Models\FundAccount;
use App\Models\FundMember;
use App\Models\Transaction;
use Illuminate\Console\Command;

/**
 * Compara los balances actuales de CapitalAccount/FundAccount contra los que
 * resultarían bajo la semántica propuesta (capital + fondo = cash en banco)
 * y contra el cash real reconstruido desde transacciones bancarias.
 *
 * No modifica datos. Solo reporta divergencias.
 */
class DiagnoseLedgers extends Command
{
    protected $signature = 'app:diagnose-ledgers';

    protected $description = 'Reporta divergencias entre los ledgers almacenados, el modelo propuesto y el cash bancario real.';

    public function handle(): int
    {
        $storedCapital = (float) CapitalAccount::instance()->balance;
        $storedFund    = (float) FundAccount::instance()->balance;

        // ── Componentes brutos ────────────────────────────────────────────
        $activeCapital = (float) FundMember::where('active', true)
            ->where(fn ($q) => $q->where('type', 'capital')
                ->orWhere(fn ($q2) => $q2->where('type', 'in_kind')->where('contribution', '>', 0))
            )
            ->sum('contribution');

        $financingsActive = Financing::whereNotIn('status', ['solicited', 'cancelled']);
        $disbursedPhysical    = (float) (clone $financingsActive)->sum('disbursed_amount');
        $commissionRetained   = (float) (clone $financingsActive)->sum('commission');
        $collectedToCapital   = (float) (clone $financingsActive)->sum('collected_amount');
        $lateFeeCollected     = (float) Financing::sum('late_fee_amount');

        $confirmed = fn (string $type) => (float) Transaction::where('type', $type)
            ->where('status', 'confirmed')->sum('amount');

        $disbursementTxn      = $confirmed('disbursement');
        $collectionTxn        = $confirmed('collection');
        $expenseTxn           = $confirmed('expense');
        $memberDisbursement   = $confirmed('member_disbursement');
        $earningsToCapital    = $confirmed('earnings_to_capital');
        $distributionTxn      = $confirmed('earning_distribution');

        // Auto-tax expenses generadas desde un member_disbursement.
        // transaction_number = 'TX' + padded(member_disbursement_id).
        $memberDisbIds = Transaction::where('type', 'member_disbursement')
            ->where('status', 'confirmed')->pluck('id');
        $taxFromMemberDisb = (float) Transaction::where('type', 'expense')
            ->where('status', 'confirmed')
            ->whereIn('transaction_number', $memberDisbIds->map(fn ($id) => 'TX' . str_pad((string) $id, 6, '0', STR_PAD_LEFT)))
            ->sum('amount');

        // Reserva de impuesto pre-debitada en cierres (de los snapshots).
        $totalTaxReserveAtClosings = 0.0;
        foreach (ClosingParametersSnapshot::where('key', 'tax_pct')->get() as $snap) {
            $taxPct = (float) $snap->value;
            if ($taxPct <= 0) continue;
            $closingDistTotal = (float) ClosingDistribution::where('monthly_closing_id', $snap->monthly_closing_id)
                ->sum('total_amount');
            $totalTaxReserveAtClosings += round($closingDistTotal * ($taxPct / 100), 2);
        }

        // ── Modelo propuesto (capital + fund = cash bancario) ─────────────
        // Capital: contribuciones (incluye earnings_to_capital ya bakeado en contribution)
        //          − físicamente desembolsado − comisión "prestada" al fondo + colectado a capital
        $proposedCapital = round(
            $activeCapital
            - $disbursedPhysical
            - $commissionRetained
            + $collectedToCapital,
            2
        );

        // Fund: comisiones + mora − gastos − retiros a miembros − capitalizaciones
        // (las distribuciones y la reserva de impuesto NO debitan el fondo en el modelo propuesto)
        $proposedFund = round(
            $commissionRetained
            + $lateFeeCollected
            - $expenseTxn
            - $memberDisbursement
            - $earningsToCapital,
            2
        );

        $proposedBank = round($proposedCapital + $proposedFund, 2);

        // ── Cash bancario real (desde eventos de cashflow) ────────────────
        // earnings_to_capital infló member.contribution sin entrar cash al banco;
        // por eso lo restamos de activeCapital para obtener el cash realmente aportado.
        $cashContributed = round($activeCapital - $earningsToCapital, 2);
        $realBank = round(
            $cashContributed
            + $collectionTxn
            - $disbursementTxn
            - $expenseTxn
            - $memberDisbursement,
            2
        );

        // ── Reporte ───────────────────────────────────────────────────────
        $this->newLine();
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('  DIAGNÓSTICO DE LEDGERS — MODELO ACTUAL vs PROPUESTO');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        $this->line('<options=bold>Componentes brutos:</>');
        $this->table(
            ['Concepto', 'Monto'],
            [
                ['Aportes activos (capital + in_kind con contribución)', $this->fmt($activeCapital)],
                ['Comisiones retenidas (financings activos)',           $this->fmt($commissionRetained)],
                ['Mora cobrada (sum late_fee_amount)',                  $this->fmt($lateFeeCollected)],
                ['Cobrado a capital (sum collected_amount)',            $this->fmt($collectedToCapital)],
                ['Desembolsado físico (sum disbursed_amount)',          $this->fmt($disbursedPhysical)],
                ['Gastos confirmados (incluye auto-tax)',               $this->fmt($expenseTxn)],
                ['Retiros a miembros (member_disbursement)',            $this->fmt($memberDisbursement)],
                ['Capitalizaciones (earnings_to_capital)',              $this->fmt($earningsToCapital)],
                ['Distribuciones (earning_distribution)',               $this->fmt($distributionTxn)],
                ['Auto-tax derivado de member_disbursement',            $this->fmt($taxFromMemberDisb)],
                ['Reserva de impuesto pre-debitada en cierres',         $this->fmt($totalTaxReserveAtClosings)],
                ['Tx desembolso (sum)',                                 $this->fmt($disbursementTxn)],
                ['Tx cobro (sum)',                                      $this->fmt($collectionTxn)],
            ]
        );

        $diffCapital = round($proposedCapital - $storedCapital, 2);
        $diffFund    = round($proposedFund - $storedFund, 2);
        $storedBank  = round($storedCapital + $storedFund, 2);
        $diffBank    = round($proposedBank - $storedBank, 2);
        $realVsProp  = round($realBank - $proposedBank, 2);

        $this->line('<options=bold>Comparación de balances:</>');
        $this->table(
            ['Ledger', 'Almacenado', 'Propuesto', 'Δ (prop − alm)'],
            [
                ['CapitalAccount', $this->fmt($storedCapital), $this->fmt($proposedCapital), $this->highlight($diffCapital)],
                ['FundAccount',    $this->fmt($storedFund),    $this->fmt($proposedFund),    $this->highlight($diffFund)],
                ['BANK = Cap+Fund', $this->fmt($storedBank),    $this->fmt($proposedBank),    $this->highlight($diffBank)],
            ]
        );

        $this->line('<options=bold>Validación contra cash real:</>');
        $this->line(sprintf(
            '  Cash bancario real (Aportes + Cobros − Desembolsos − Gastos − Retiros): %s',
            $this->fmt($realBank)
        ));
        $this->line(sprintf('  Bank propuesto (Cap + Fund nuevo modelo): %s', $this->fmt($proposedBank)));
        $this->line(sprintf('  Δ (real − propuesto): %s', $this->highlight($realVsProp)));

        if (abs($realVsProp) < 0.01) {
            $this->info('  ✓ El modelo propuesto cuadra exactamente con el cash bancario real.');
        } else {
            $this->error('  ✗ El modelo propuesto no cuadra con el cash bancario real. Revisar supuestos.');
        }

        $this->newLine();
        $this->line('<options=bold>Origen esperado de la divergencia del FundAccount almacenado:</>');
        $expectedDiff = round(
            $distributionTxn                  // distribuciones que el modelo viejo descontó del fondo
            + $totalTaxReserveAtClosings       // reservas de impuesto pre-descontadas
            - $memberDisbursement              // retiros que el modelo viejo NO descontó
            - $taxFromMemberDisb               // taxes-de-retiro que el modelo viejo NO descontó
            - $earningsToCapital,              // capitalizaciones que el modelo viejo NO descontó
            2
        );
        $this->line(sprintf('  Δ FundAccount esperado por la teoría: %s', $this->highlight($expectedDiff)));
        $this->line(sprintf('  Δ FundAccount observado (prop − alm):  %s', $this->highlight($diffFund)));

        if (abs($expectedDiff - $diffFund) < 0.01) {
            $this->info('  ✓ La divergencia coincide con la hipótesis del refactor.');
        } else {
            $this->warn('  ⚠ La divergencia no coincide exactamente — puede haber otra causa.');
        }

        $this->newLine();
        return self::SUCCESS;
    }

    private function fmt(float $v): string
    {
        return 'RD$ ' . number_format($v, 2, '.', ',');
    }

    private function highlight(float $v): string
    {
        if (abs($v) < 0.01) {
            return '<fg=green>' . $this->fmt($v) . '</>';
        }
        return '<fg=yellow>' . $this->fmt($v) . '</>';
    }
}
