<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `transactions` MODIFY `type` ENUM('disbursement', 'collection', 'expense') NOT NULL");
        DB::statement("ALTER TABLE `transactions` MODIFY `bank` VARCHAR(255) NULL");
        DB::statement("ALTER TABLE `transactions` MODIFY `transaction_number` VARCHAR(255) NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `transactions` MODIFY `bank` VARCHAR(255) NOT NULL");
        DB::statement("ALTER TABLE `transactions` MODIFY `transaction_number` VARCHAR(255) NOT NULL");
        DB::statement("ALTER TABLE `transactions` MODIFY `type` ENUM('disbursement', 'collection') NOT NULL");
    }
};
