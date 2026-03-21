<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `transactions` MODIFY `type` ENUM('disbursement', 'collection', 'expense', 'earning_distribution', 'member_disbursement') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `transactions` MODIFY `type` ENUM('disbursement', 'collection', 'expense') NOT NULL");
    }
};
