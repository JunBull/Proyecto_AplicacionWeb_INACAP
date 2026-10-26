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
        Schema::create('bajas_reemplazos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_id')->constrained('equipos')->restrictOnDelete();
            $table->foreignId('falla_id')->nullable()->constrained('fallas')->restrictOnDelete();
            $table->enum('tipo', ['baja', 'reemplazo']);
            $table->text('motivo');
            $table->enum('estado', ['propuesta', 'aprobada', 'rechazada'])->default('propuesta');
            $table->foreignId('propuesta_por')->constrained('usuarios')->restrictOnDelete();
            $table->foreignId('aprobada_por')->nullable()->constrained('usuarios')->restrictOnDelete();
            $table->date('fecha');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bajas_reemplazos');
    }
};
