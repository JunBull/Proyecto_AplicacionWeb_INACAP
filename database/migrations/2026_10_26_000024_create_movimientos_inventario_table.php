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
        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insumo_id')->constrained('insumos')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->foreignId('compra_id')->nullable()->constrained('compras')->restrictOnDelete();
            $table->foreignId('solicitud_id')->nullable()->constrained('solicitudes_material')->restrictOnDelete();
            $table->enum('tipo', ['ingreso_compra', 'ingreso_manual', 'salida_prestamo', 'salida_consumo', 'reingreso_devolucion', 'ajuste']);
            $table->decimal('cantidad', 10, 2);
            $table->timestamp('fecha')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimientos_inventario');
    }
};
