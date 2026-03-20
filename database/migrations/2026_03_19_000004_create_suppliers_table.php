<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('rnc')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Proveedor por defecto para impuestos automáticos
        DB::table('suppliers')->insert([
            'name'       => 'DGII',
            'rnc'        => null,
            'active'     => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
