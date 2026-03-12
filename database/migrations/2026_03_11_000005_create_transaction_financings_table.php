<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_financings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financing_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['transaction_id', 'financing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_financings');
    }
};
