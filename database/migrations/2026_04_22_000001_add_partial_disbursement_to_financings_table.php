<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financings', function (Blueprint $table) {
            $table->decimal('disbursed_amount', 15, 2)->default(0.00)->after('transfer_amount');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE financings MODIFY COLUMN status ENUM('solicited','partially_disbursed','disbursed','pending_payment','partially_collected','collected','cancelled') DEFAULT 'solicited'");
        }

        // Backfill: financiamientos ya desembolsados tienen disbursed_amount == transfer_amount
        DB::table('financings')
            ->whereIn('status', ['disbursed', 'pending_payment', 'partially_collected', 'collected'])
            ->update(['disbursed_amount' => DB::raw('transfer_amount')]);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE financings MODIFY COLUMN status ENUM('solicited','disbursed','pending_payment','partially_collected','collected','cancelled') DEFAULT 'solicited'");
        }

        Schema::table('financings', function (Blueprint $table) {
            $table->dropColumn('disbursed_amount');
        });
    }
};
