<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add partially_collected to status enum (MySQL only; SQLite treats enum as text)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE financings MODIFY COLUMN status ENUM('solicited','disbursed','partially_collected','collected','cancelled') DEFAULT 'solicited'");
        }

        // Add collected_amount to track partial payments
        Schema::table('financings', function (Blueprint $table) {
            $table->decimal('collected_amount', 15, 2)->default(0.00)->after('transfer_amount');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE financings MODIFY COLUMN status ENUM('solicited','disbursed','collected','cancelled') DEFAULT 'solicited'");
        }

        Schema::table('financings', function (Blueprint $table) {
            $table->dropColumn('collected_amount');
        });
    }
};
