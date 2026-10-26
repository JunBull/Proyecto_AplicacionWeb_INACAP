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
        Schema::create('mantenimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_id')->constrained('equipos')->restrictOnDelete();
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->restrictOnDelete();
            $table->enum('tipo', ['preventivo', 'correctivo']);
            $table->enum('frecuencia', ['semestral', 'anual', 'no_aplica'])->nullable();
            $table->date('fecha_programada');
            $table->date('fecha_realizada')->nullable();
            $table->enum('estado', ['programado', 'en_proceso', 'realizado', 'vencido', 'cancelado'])->default('programado');
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mantenimientos');
    }
};
