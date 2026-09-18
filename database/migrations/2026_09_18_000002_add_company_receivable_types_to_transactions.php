<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tipos para el libro de cuentas por cobrar a compañías.
 *
 * El fondo puede quedar acreedor de una compañía sin que medie un financiamiento:
 * el caso que originó esto fue el sobre-desembolso de FN000130, donde se entregó
 * efectivo por encima del transfer_amount correcto. Hasta ahora ese saldo no tenía
 * dónde vivir y terminaba disfrazado de deuda interna fondo→capital vía el
 * préstamo automático de TransactionService.
 *
 *   company_receivable : nace el saldo. NO mueve capital ni fondo — el efectivo ya
 *                        salió cuando se hizo el desembolso y los ledgers ya lo
 *                        reflejan. Es el asiento de trazabilidad que faltaba.
 *   company_repayment  : la compañía devuelve el efectivo. Acredita capital.
 *
 * Para netear contra un desembolso futuro se registran ambos movimientos con la
 * misma fecha en vez de comprimirlos: el efectivo neto es el mismo y quedan dos
 * asientos auditables, sin tocar el flujo de desembolso.
 */
return new class extends Migration
{
    private const TYPES_AFTER = "'disbursement', 'collection', 'expense', 'earning_distribution', 'member_disbursement', 'earnings_to_capital', 'fund_loan_to_capital', 'capital_repayment_to_fund', 'company_receivable', 'company_repayment'";

    private const TYPES_BEFORE = "'disbursement', 'collection', 'expense', 'earning_distribution', 'member_disbursement', 'earnings_to_capital', 'fund_loan_to_capital', 'capital_repayment_to_fund'";

    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE `transactions` MODIFY `type` ENUM(' . self::TYPES_AFTER . ') NOT NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE `transactions` MODIFY `type` ENUM(' . self::TYPES_BEFORE . ') NOT NULL');
        }
    }
};
