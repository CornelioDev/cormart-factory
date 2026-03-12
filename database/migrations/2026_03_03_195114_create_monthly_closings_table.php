<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_closings', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7)->unique();
            $table->decimal('total_commissions', 15, 2);
            $table->decimal('total_fixed', 15, 2);
            $table->decimal('net_profit', 15, 2);
            $table->decimal('reserve', 15, 2);
            $table->decimal('post_reserve', 15, 2);
            $table->decimal('in_kind_payment', 15, 2);
            $table->decimal('available_for_capital', 15, 2);
            $table->decimal('verification_diff', 15, 2)->default(0);
            $table->foreignId('executed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_closings');
    }
};