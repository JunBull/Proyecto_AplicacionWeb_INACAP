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
        // 1. Tabla de Asignaturas / Talleres Prácticos
        Schema::create('asignaturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrera_id')->constrained('carreras')->cascadeOnDelete();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 150);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // 2. Tabla pivote Asignatura - Docente (asignatura_usuario)
        Schema::create('asignatura_usuario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asignatura_id')->constrained('asignaturas')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->unique(['asignatura_id', 'usuario_id']);
            $table->timestamps();
        });

        // 3. Parámetros del Sistema (Configuración Global del Administrador)
        Schema::create('parametros_sistema', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 100)->unique();
            $table->text('valor')->nullable();
            $table->string('tipo', 30)->default('string'); // integer, boolean, string
            $table->string('descripcion', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parametros_sistema');
        Schema::dropIfExists('asignatura_usuario');
        Schema::dropIfExists('asignaturas');
    }
};
