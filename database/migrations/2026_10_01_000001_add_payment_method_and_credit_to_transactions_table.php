<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cobros en cheque y saldo a favor del fondo.
 *
 * `payment_method` distingue el cobro que entra al banco del fondo (transfer) del
 * que queda en manos de la compañía (check). `credit_applied` registra cuánto de
 * ese saldo se usó para cubrir un desembolso, que entonces transfiere solo la
 * diferencia — de ahí que banco y número pasen a ser opcionales.
 *
 * El enum `type` se extiende con el mismo ALTER crudo que usa
 * 2026_09_18_000002: un `->change()` de Laravel sobre el enum reescribiría la
 * lista completa y borraría los tipos agregados después del fork.
 */
return new class extends Migration
{
    private const TYPES_AFTER = "'disbursement', 'collection', 'expense', 'earning_distribution', 'member_disbursement', 'earnings_to_capital', 'fund_loan_to_capital', 'capital_repayment_to_fund', 'company_receivable', 'company_repayment', 'settlement'";

    private const TYPES_BEFORE = "'disbursement', 'collection', 'expense', 'earning_distribution', 'member_disbursement', 'earnings_to_capital', 'fund_loan_to_capital', 'capital_repayment_to_fund', 'company_receivable', 'company_repayment'";

    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('payment_method', ['transfer', 'check'])
                  ->default('transfer')
                  ->after('status')
                  ->comment('Cómo pagó el deudor: transferencia al fondo o cheque en manos de la compañía');

            $table->decimal('credit_applied', 15, 2)
                  ->default(0)
                  ->after('amount')
                  ->comment('Saldo a favor del fondo aplicado a este desembolso');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE `transactions` MODIFY `type` ENUM(' . self::TYPES_AFTER . ') NOT NULL');
        }

        Schema::table('transactions', function (Blueprint $table) {
            // Un desembolso cubierto al 100% con saldo no genera transferencia bancaria.
            // El índice unique sobre transaction_number tolera varios NULL en MySQL.
            $table->string('bank')->nullable()->comment('Nombre del banco')->change();
            $table->string('transaction_number')->nullable()
                  ->comment('Número de transacción bancaria o de cheque')->change();
        });
    }

    public function down(): void
    {
        DB::table('transactions')->whereNull('bank')->update(['bank' => '']);
        DB::table('transactions')->whereNull('transaction_number')->update(['transaction_number' => '']);
        DB::table('transactions')->where('type', 'settlement')->delete();

        Schema::table('transactions', function (Blueprint $table) {
            $table->string('bank')->nullable(false)->comment('Nombre del banco')->change();
            $table->string('transaction_number')->nullable(false)
                  ->comment('Número de transacción bancaria')->change();
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE `transactions` MODIFY `type` ENUM(' . self::TYPES_BEFORE . ') NOT NULL');
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'credit_applied']);
        });
    }
};
