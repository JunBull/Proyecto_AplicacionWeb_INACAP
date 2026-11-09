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
        Schema::table('solicitudes_material', function (Blueprint $table) {
            $table->string('tipo_destino', 20)->nullable()->after('ubicacion_destino_id'); // 'sala' | 'laboratorio'
            $table->string('edificio_destino', 50)->nullable()->after('tipo_destino');      // 'Edificio A' | 'Edificio C'
            $table->string('espacio_destino', 100)->nullable()->after('edificio_destino');   // 'Sala A-101', 'Laboratorio L1', etc.
            $table->string('motivo_cancelacion', 255)->nullable()->after('observaciones');
        });

        // Ampliación de CHECK constraint en PostgreSQL para admitir estado 'cancelada'
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE solicitudes_material DROP CONSTRAINT IF EXISTS solicitudes_material_estado_check');
            DB::statement("ALTER TABLE solicitudes_material ADD CONSTRAINT solicitudes_material_estado_check CHECK (((estado)::text = ANY ((ARRAY['pendiente'::character varying, 'aprobada'::character varying, 'rechazada'::character varying, 'sin_disponibilidad'::character varying, 'entregada_abierta'::character varying, 'cerrada'::character varying, 'cancelada'::character varying])::text[])))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE solicitudes_material DROP CONSTRAINT IF EXISTS solicitudes_material_estado_check');
            DB::statement("ALTER TABLE solicitudes_material ADD CONSTRAINT solicitudes_material_estado_check CHECK (((estado)::text = ANY ((ARRAY['pendiente'::character varying, 'aprobada'::character varying, 'rechazada'::character varying, 'sin_disponibilidad'::character varying, 'entregada_abierta'::character varying, 'cerrada'::character varying])::text[])))");
        }

        Schema::table('solicitudes_material', function (Blueprint $table) {
            $table->dropColumn([
                'tipo_destino',
                'edificio_destino',
                'espacio_destino',
                'motivo_cancelacion',
            ]);
        });
    }
};
