<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parameters_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parameter_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->decimal('previous_value', 8, 4);
            $table->decimal('new_value', 8, 4);
            $table->string('period', 7);
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parameters_history');
    }
};