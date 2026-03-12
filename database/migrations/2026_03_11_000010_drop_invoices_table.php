<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('invoices');
    }

    public function down(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number')->unique();
            $table->date('invoice_date');
            $table->string('issue_period', 7)->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('commission', 12, 2);
            $table->decimal('transfer_amount', 12, 2);
            $table->unsignedInteger('term_days')->default(15);
            $table->date('due_date');
            $table->string('status', 20)->default('pending');
            $table->date('collected_at')->nullable();
            $table->string('collection_period', 7)->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }
};
