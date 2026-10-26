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
        Schema::create('entregas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('solicitudes_material')->restrictOnDelete();
            $table->foreignId('entregado_por')->constrained('usuarios')->restrictOnDelete();
            $table->string('receptor_nombre');
            $table->enum('receptor_tipo', ['docente', 'estudiante']);
            $table->timestamp('fecha_entrega')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entregas');
    }
};
