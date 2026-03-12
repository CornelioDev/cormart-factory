<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->date('invoice_date');
            $table->string('issue_period', 7);
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->decimal('commission', 15, 2);
            $table->decimal('transfer_amount', 15, 2);
            $table->integer('term_days')->default(15);
            $table->date('due_date');
            $table->enum('status', ['pending', 'collected', 'overdue'])->default('pending');
            $table->date('collected_at')->nullable();
            $table->string('collection_period', 7)->nullable();
            $table->foreignId('registered_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};