<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('closing_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_closing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fund_member_id')->constrained()->restrictOnDelete();
            $table->decimal('fixed_amount', 15, 2)->default(0);
            $table->decimal('proportional_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('closing_distributions');
    }
};