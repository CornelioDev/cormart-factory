<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financing_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financing_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['purchase_order', 'invoice']);
            $table->string('document_number')->nullable()->comment('Número de OC o factura');
            $table->date('document_date')->nullable();
            $table->string('file_path')->nullable()->comment('Ruta del adjunto en storage');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financing_documents');
    }
};
