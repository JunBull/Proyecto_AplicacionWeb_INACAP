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
        Schema::create('cotizaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_cotizacion_id')->constrained('solicitudes_cotizacion')->restrictOnDelete();
            $table->foreignId('proveedor_id')->constrained('proveedores')->restrictOnDelete();
            $table->decimal('monto_total', 14, 2);
            $table->date('fecha_recepcion');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotizaciones');
    }
};
