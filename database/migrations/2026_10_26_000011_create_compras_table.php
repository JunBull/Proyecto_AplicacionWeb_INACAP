<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adjudicacion_id')->nullable()->constrained('adjudicaciones')->restrictOnDelete();
            $table->foreignId('proveedor_id')->constrained('proveedores')->restrictOnDelete();
            $table->foreignId('carrera_id')->constrained('carreras')->restrictOnDelete();
            $table->string('numero_documento');
            $table->date('fecha');
            $table->decimal('monto_total', 14, 2);
            $table->enum('estado', ['registrada', 'pendiente_recepcion', 'recibida_con_diferencias', 'ingresada_inventario', 'anulada'])->default('registrada');
            $table->unique(['proveedor_id', 'numero_documento']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compras');
    }
};
