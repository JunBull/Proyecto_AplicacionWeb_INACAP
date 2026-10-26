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
        Schema::create('fallas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_id')->constrained('equipos')->restrictOnDelete();
            $table->foreignId('reportada_por')->constrained('usuarios')->restrictOnDelete();
            $table->foreignId('atendida_por')->nullable()->constrained('usuarios')->restrictOnDelete();
            $table->text('descripcion');
            $table->timestamp('fecha_reporte')->useCurrent();
            $table->enum('estado', ['reportada', 'en_revision', 'en_reparacion', 'resuelta', 'derivada_a_baja'])->default('reportada');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fallas');
    }
};
