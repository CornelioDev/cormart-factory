<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_closings', function (Blueprint $table) {
            $table->decimal('total_expenses', 15, 2)->default(0)->after('total_commissions');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_closings', function (Blueprint $table) {
            $table->dropColumn('total_expenses');
        });
    }
};
