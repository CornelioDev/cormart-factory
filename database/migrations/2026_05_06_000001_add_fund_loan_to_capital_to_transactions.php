<?php

use App\Models\Parameter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE `transactions` MODIFY `type` ENUM('disbursement', 'collection', 'expense', 'earning_distribution', 'member_disbursement', 'earnings_to_capital', 'fund_loan_to_capital', 'capital_repayment_to_fund') NOT NULL");
        }

        Parameter::firstOrCreate(
            ['key' => 'allow_fund_loan_to_capital'],
            [
                'value'       => '0',
                'description' => 'Permitir que el fondo preste cash al capital cuando un desembolso lo requiera (1 = sí, 0 = no)',
            ],
        );
    }

    public function down(): void
    {
        Parameter::where('key', 'allow_fund_loan_to_capital')->delete();

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE `transactions` MODIFY `type` ENUM('disbursement', 'collection', 'expense', 'earning_distribution', 'member_disbursement', 'earnings_to_capital') NOT NULL");
        }
    }
};
