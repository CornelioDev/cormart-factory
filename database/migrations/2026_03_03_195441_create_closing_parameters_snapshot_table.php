<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('closing_parameters_snapshot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_closing_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->decimal('value', 8, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('closing_parameters_snapshot');
    }
};