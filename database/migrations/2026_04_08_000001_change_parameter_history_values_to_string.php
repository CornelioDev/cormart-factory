<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parameters_history', function (Blueprint $table) {
            $table->string('previous_value')->change();
            $table->string('new_value')->change();
        });
    }

    public function down(): void
    {
        Schema::table('parameters_history', function (Blueprint $table) {
            $table->decimal('previous_value', 8, 4)->change();
            $table->decimal('new_value', 8, 4)->change();
        });
    }
};
