<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // El nombre del deudor solo debe ser único dentro de su compañía,
            // no de forma global. Dos compañías pueden tener un deudor homónimo.
            $table->dropUnique('clients_name_unique');
            $table->unique(['company_id', 'name'], 'clients_company_id_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique('clients_company_id_name_unique');
            $table->unique('name', 'clients_name_unique');
        });
    }
};
