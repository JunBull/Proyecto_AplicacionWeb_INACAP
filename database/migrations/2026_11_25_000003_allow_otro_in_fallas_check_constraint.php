<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE fallas DROP CONSTRAINT IF EXISTS chk_fallas_equipo_o_insumo');
            DB::statement('ALTER TABLE fallas ADD CONSTRAINT chk_fallas_recurso_valido CHECK ((equipo_id IS NOT NULL AND insumo_id IS NULL) OR (equipo_id IS NULL AND insumo_id IS NOT NULL) OR (equipo_id IS NULL AND insumo_id IS NULL))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE fallas DROP CONSTRAINT IF EXISTS chk_fallas_recurso_valido');
            DB::statement('ALTER TABLE fallas ADD CONSTRAINT chk_fallas_equipo_o_insumo CHECK ((equipo_id IS NOT NULL AND insumo_id IS NULL) OR (equipo_id IS NULL AND insumo_id IS NOT NULL))');
        }
    }
};
