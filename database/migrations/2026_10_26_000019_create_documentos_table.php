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
        Schema::create('documentos', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['factura', 'orden_compra', 'cotizacion', 'informe_tecnico', 'informe_proveedor', 'otro']);
            $table->string('ruta');
            $table->string('nombre_original');
            $table->string('hash_sha256', 64)->index();
            $table->string('documentable_type');
            $table->unsignedBigInteger('documentable_id');
            $table->foreignId('subido_por')->constrained('usuarios')->restrictOnDelete();
            $table->timestamp('creado_en')->useCurrent();
            $table->timestamps();

            $table->index(['documentable_type', 'documentable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documentos');
    }
};
