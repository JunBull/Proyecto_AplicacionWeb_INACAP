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
        Schema::create('solicitudes_material', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitante_id')->constrained('usuarios')->restrictOnDelete();
            $table->foreignId('revisada_por')->nullable()->constrained('usuarios')->restrictOnDelete();
            $table->timestamp('fecha_solicitud')->useCurrent();
            $table->date('fecha_requerida');
            $table->enum('estado', ['pendiente', 'aprobada', 'rechazada', 'sin_disponibilidad', 'entregada_abierta', 'cerrada'])->default('pendiente');
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitudes_material');
    }
};
