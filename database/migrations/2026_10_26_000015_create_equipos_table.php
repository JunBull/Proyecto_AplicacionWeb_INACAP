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
        Schema::create('equipos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_barras')->unique();
            $table->string('nombre');
            $table->string('marca');
            $table->string('modelo');
            $table->string('numero_serie');
            $table->enum('estado', ['disponible', 'prestado', 'en_mantenimiento', 'no_disponible', 'pendiente_resolucion', 'dado_de_baja'])->default('disponible');
            $table->enum('criticidad', ['alta', 'media', 'baja'])->default('media');
            $table->foreignId('ubicacion_id')->constrained('ubicaciones')->restrictOnDelete();
            $table->foreignId('compra_id')->nullable()->constrained('compras')->nullOnDelete();
            $table->string('fotografia')->default('equipos/default.png');
            $table->date('fecha_adquisicion')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipos');
    }
};
