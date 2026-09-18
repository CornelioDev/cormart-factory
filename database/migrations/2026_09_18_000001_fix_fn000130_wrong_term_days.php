<?php

use App\Models\CapitalAccount;
use App\Models\Financing;
use App\Models\FundAccount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * FN000130 (id 130) se registró con term_days = 15 cuando el plazo pactado era
 * de 60 días. El plazo no es un dato aislado: FinancingService::calculateCommission
 * aplica un multiplicador ceil(term_days / 30), así que a 15 días se cobró
 * comisión simple (19,765.59) en vez de la doble que corresponde a 60 días
 * (39,531.18 = 395,311.80 × 5% × 2).
 *
 * Corrige el financiamiento y ambos ledgers:
 *
 *   term_days       15          -> 60
 *   commission      19,765.59   -> 39,531.18
 *   transfer_amount 375,546.21  -> 355,780.62
 *   due_date        2026-09-30  -> 2026-11-14   (confirmed_at 2026-09-15 + 60d)
 *
 * El financiamiento ya se había desembolsado completo en 6 partidas por
 * 375,546.21 bajo la comisión vieja, así que disbursed_amount queda 19,765.59
 * por encima del nuevo transfer_amount. Ese sobre-desembolso es real: el efectivo
 * está en manos de la compañía y se recupera por separado. No se toca
 * disbursed_amount porque refleja la salida física de caja.
 *
 * Ledgers (ver LedgerVerificationService): la comisión retenida se resta del
 * capital y se suma al fondo, de modo que +19,765.59 de comisión mueve esa misma
 * cantidad de CapitalAccount a FundAccount. Sin este ajuste las verificaciones
 * contables empezarían a fallar y a notificar.
 *
 * No afecta cierres: collection_period es NULL y el último cierre es 2026-08,
 * así que esta comisión todavía no entró en ninguna distribución.
 *
 * Idempotente: solo aplica si el financiamiento sigue con el plazo equivocado.
 */
return new class extends Migration
{
    private const FINANCING_ID = 130;
    private const NEW_TERM_DAYS = 60;
    private const NEW_COMMISSION = 39531.18;
    private const NEW_TRANSFER_AMOUNT = 355780.62;
    private const NEW_DUE_DATE = '2026-11-14';
    private const COMMISSION_DELTA = 19765.59;

    public function up(): void
    {
        $financing = Financing::find(self::FINANCING_ID);

        // Solo aplicar sobre el estado exacto que esta corrección espera.
        if (! $financing
            || (int) $financing->term_days !== 15
            || abs((float) $financing->commission - 19765.59) > 0.01) {
            return;
        }

        DB::transaction(function () use ($financing) {
            $financing->update([
                'term_days'       => self::NEW_TERM_DAYS,
                'commission'      => self::NEW_COMMISSION,
                'transfer_amount' => self::NEW_TRANSFER_AMOUNT,
                'due_date'        => self::NEW_DUE_DATE,
            ]);

            DB::table('capital_account')
                ->where('id', CapitalAccount::instance()->id)
                ->decrement('balance', self::COMMISSION_DELTA);

            DB::table('fund_account')
                ->where('id', FundAccount::instance()->id)
                ->increment('balance', self::COMMISSION_DELTA);
        });
    }

    public function down(): void
    {
        $financing = Financing::find(self::FINANCING_ID);

        if (! $financing
            || (int) $financing->term_days !== self::NEW_TERM_DAYS
            || abs((float) $financing->commission - self::NEW_COMMISSION) > 0.01) {
            return;
        }

        DB::transaction(function () use ($financing) {
            $financing->update([
                'term_days'       => 15,
                'commission'      => 19765.59,
                'transfer_amount' => 375546.21,
                'due_date'        => '2026-09-30',
            ]);

            DB::table('capital_account')
                ->where('id', CapitalAccount::instance()->id)
                ->increment('balance', self::COMMISSION_DELTA);

            DB::table('fund_account')
                ->where('id', FundAccount::instance()->id)
                ->decrement('balance', self::COMMISSION_DELTA);
        });
    }
};
