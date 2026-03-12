<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['disbursement', 'collection']);
            $table->enum('status', ['pending', 'confirmed'])->default('pending');
            $table->decimal('amount', 15, 2);

            // Datos bancarios (obligatorios — entrada manual)
            $table->string('bank')->comment('Nombre del banco');
            $table->string('transaction_number')->comment('Número de transacción bancaria');
            $table->date('transaction_date');

            // Para disbursements: compañía que recibe
            $table->foreignId('company_id')->nullable()->constrained()->restrictOnDelete();

            $table->string('notes')->nullable();
            $table->foreignId('registered_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
