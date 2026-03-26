<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();

            // Montos
            $table->decimal('amount', 15, 2)->comment('Monto del financiamiento');
            $table->decimal('commission', 15, 2)->comment('Comisión calculada');
            $table->decimal('transfer_amount', 15, 2)->comment('Monto neto transferido');

            // Plazos
            $table->unsignedSmallInteger('term_days')->default(15);
            $table->date('request_date');
            $table->date('due_date')->nullable();

            // Estado
            $table->enum('status', ['solicited', 'disbursed', 'pending_payment', 'partially_collected', 'collected', 'cancelled'])
                  ->default('solicited');
            $table->string('cancellation_reason')->nullable();

            // Periodos (para cierre mensual)
            $table->string('issue_period', 7)->nullable()->comment('YYYY-MM del desembolso');
            $table->string('collection_period', 7)->nullable()->comment('YYYY-MM de la cobranza');

            // Auditoría
            $table->date('disbursed_at')->nullable();
            $table->date('collected_at')->nullable();
            $table->foreignId('registered_by')->constrained('users')->restrictOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financings');
    }
};
