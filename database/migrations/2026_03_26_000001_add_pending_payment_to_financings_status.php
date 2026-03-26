<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE financings MODIFY COLUMN status ENUM('solicited','disbursed','pending_payment','partially_collected','collected','cancelled') DEFAULT 'solicited'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE financings MODIFY COLUMN status ENUM('solicited','disbursed','partially_collected','collected','cancelled') DEFAULT 'solicited'");
        }
    }
};
