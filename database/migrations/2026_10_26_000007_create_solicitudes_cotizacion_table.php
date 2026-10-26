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
        Schema::create('solicitudes_cotizacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrera_id')->constrained('carreras')->restrictOnDelete();
            $table->foreignId('creada_por')->constrained('usuarios')->restrictOnDelete();
            $table->text('descripcion');
            $table->enum('estado', ['borrador', 'enviada', 'en_evaluacion', 'adjudicada', 'cancelada'])->default('borrador');
            $table->timestamp('fecha_creacion')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitudes_cotizacion');
    }
};
