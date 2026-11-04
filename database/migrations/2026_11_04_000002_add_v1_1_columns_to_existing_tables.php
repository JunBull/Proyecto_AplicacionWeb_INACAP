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
        // 1. Columnas aditivas sobre solicitudes_material
        Schema::table('solicitudes_material', function (Blueprint $table) {
            $table->foreignId('asignatura_id')->nullable()->after('revisada_por')->constrained('asignaturas')->nullOnDelete();
            $table->foreignId('ubicacion_destino_id')->nullable()->after('asignatura_id')->constrained('ubicaciones')->nullOnDelete();
            $table->time('hora_requerida')->nullable()->after('fecha_requerida');
        });

        // 2. Columna aditiva sobre insumos
        Schema::table('insumos', function (Blueprint $table) {
            $table->integer('stock_minimo_clases')->default(2)->after('stock_minimo');
        });

        // 3. Columna aditiva sobre equipos
        Schema::table('equipos', function (Blueprint $table) {
            $table->boolean('reservado_clases')->default(false)->after('criticidad');
        });

        // 4. Columnas aditivas sobre entregas
        Schema::table('entregas', function (Blueprint $table) {
            $table->string('receptor_credencial_rut', 30)->nullable()->after('receptor_tipo');
            $table->boolean('credencial_verificada')->default(false)->after('receptor_credencial_rut');
            $table->time('hora_entrega')->nullable()->after('fecha_entrega');
            $table->time('hora_devolucion_acordada')->nullable()->after('hora_entrega');
        });

        // 5. Extensión de fallas (Reporte de incidencias para equipos e insumos)
        Schema::table('fallas', function (Blueprint $table) {
            $table->unsignedBigInteger('equipo_id')->nullable()->change();
            $table->foreignId('insumo_id')->nullable()->after('equipo_id')->constrained('insumos')->nullOnDelete();
            $table->foreignId('ubicacion_id')->nullable()->after('insumo_id')->constrained('ubicaciones')->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE fallas ADD CONSTRAINT chk_fallas_equipo_o_insumo CHECK ((equipo_id IS NOT NULL AND insumo_id IS NULL) OR (equipo_id IS NULL AND insumo_id IS NOT NULL))');
            DB::statement('ALTER TABLE insumos ADD CONSTRAINT chk_insumos_stock_minimo_clases CHECK (stock_minimo_clases >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE fallas DROP CONSTRAINT IF EXISTS chk_fallas_equipo_o_insumo');
            DB::statement('ALTER TABLE insumos DROP CONSTRAINT IF EXISTS chk_insumos_stock_minimo_clases');
        }

        Schema::table('fallas', function (Blueprint $table) {
            $table->dropForeign(['ubicacion_id']);
            $table->dropForeign(['insumo_id']);
            $table->dropColumn(['ubicacion_id', 'insumo_id']);
        });

        Schema::table('entregas', function (Blueprint $table) {
            $table->dropColumn([
                'receptor_credencial_rut',
                'credencial_verificada',
                'hora_entrega',
                'hora_devolucion_acordada',
            ]);
        });

        Schema::table('equipos', function (Blueprint $table) {
            $table->dropColumn(['reservado_clases']);
        });

        Schema::table('insumos', function (Blueprint $table) {
            $table->dropColumn(['stock_minimo_clases']);
        });

        Schema::table('solicitudes_material', function (Blueprint $table) {
            $table->dropForeign(['ubicacion_destino_id']);
            $table->dropForeign(['asignatura_id']);
            $table->dropColumn([
                'asignatura_id',
                'ubicacion_destino_id',
                'hora_requerida',
            ]);
        });
    }
};
