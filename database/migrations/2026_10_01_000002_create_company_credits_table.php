<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Libro de saldos de las compañías frente al fondo.
 *
 * Cada fila guarda además la composición del movimiento: cuánto es capital
 * recuperado y cuánto es mora. Sin ese desglose la liquidación no sabría a qué
 * ledger acreditar, y la mora terminaría cayendo en capital en vez de en las
 * ganancias del fondo, distorsionando el cierre del período.
 *
 * Invariante por fila: amount = capital_amount + late_fee_amount.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_credits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->restrictOnDelete();

            $table->enum('type', ['accrual', 'application', 'settlement'])
                  ->comment('accrual: cobro en cheque | application: aplicado a desembolso | settlement: liquidado por transferencia');

            // Con signo: positivo acumula saldo a favor del fondo, negativo lo consume.
            $table->decimal('amount', 15, 2);
            $table->decimal('capital_amount', 15, 2)->default(0)
                  ->comment('Parte del movimiento que es capital recuperado — acredita CapitalAccount al liquidarse');
            $table->decimal('late_fee_amount', 15, 2)->default(0)
                  ->comment('Parte del movimiento que es mora — acredita FundAccount al liquidarse');

            $table->string('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->timestamps();

            $table->index('company_id');
            $table->index(['transaction_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_credits');
    }
};
