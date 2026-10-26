<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('detalle_solicitud', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('solicitudes_material')->cascadeOnDelete();
            $table->foreignId('insumo_id')->nullable()->constrained('insumos')->restrictOnDelete();
            $table->foreignId('equipo_id')->nullable()->constrained('equipos')->restrictOnDelete();
            $table->decimal('cantidad_solicitada', 10, 2);
            $table->decimal('cantidad_entregada', 10, 2)->default(0);
            $table->decimal('cantidad_devuelta', 10, 2)->default(0);
            $table->enum('estado_devolucion', ['no_aplica', 'pendiente', 'parcial', 'completa', 'con_danos'])->default('no_aplica');
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE detalle_solicitud ADD CONSTRAINT chk_detalle_solicitud_item CHECK ((insumo_id IS NOT NULL AND equipo_id IS NULL) OR (insumo_id IS NULL AND equipo_id IS NOT NULL))');
            DB::statement('ALTER TABLE detalle_solicitud ADD CONSTRAINT chk_detalle_solicitud_cantidades CHECK (cantidad_solicitada > 0 AND cantidad_entregada >= 0 AND cantidad_devuelta >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_solicitud');
    }
};
