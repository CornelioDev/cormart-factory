<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('code', 10)->nullable()->unique()->after('id');
        });

        // Backfill existing transactions
        DB::table('transactions')->whereNull('code')->orderBy('id')->each(function ($tx) {
            DB::table('transactions')->where('id', $tx->id)->update([
                'code' => 'TX' . str_pad($tx->id, 6, '0', STR_PAD_LEFT),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
