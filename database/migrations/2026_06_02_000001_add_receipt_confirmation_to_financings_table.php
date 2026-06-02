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
            $table->date('confirmed_at')->nullable()->after('disbursed_at');
            $table->foreignId('confirmed_by')->nullable()->after('confirmed_at')
                ->constrained('users')->nullOnDelete();
        });

        DB::table('financings')
            ->whereIn('status', ['disbursed', 'partially_collected', 'collected'])
            ->whereNotNull('disbursed_at')
            ->update(['confirmed_at' => DB::raw('disbursed_at')]);
    }

    public function down(): void
    {
        Schema::table('financings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropColumn('confirmed_at');
        });
    }
};
