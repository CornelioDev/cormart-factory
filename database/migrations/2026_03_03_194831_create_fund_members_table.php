<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_members', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['capital', 'in_kind']);
            $table->decimal('contribution', 15, 2)->default(0);
            $table->decimal('fund_percentage', 8, 4)->default(0);
            $table->boolean('active')->default(true);
            $table->date('joined_at');
            $table->date('left_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_members');
    }
};