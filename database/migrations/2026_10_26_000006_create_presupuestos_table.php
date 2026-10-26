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
        Schema::create('presupuestos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrera_id')->constrained('carreras')->restrictOnDelete();
            $table->smallInteger('anio');
            $table->decimal('monto_asignado', 14, 2);
            $table->decimal('saldo_actual', 14, 2);
            $table->unique(['carrera_id', 'anio']);
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE presupuestos ADD CONSTRAINT chk_presupuestos_monto_asignado CHECK (monto_asignado >= 0)');
            DB::statement('ALTER TABLE presupuestos ADD CONSTRAINT chk_presupuestos_saldo_actual CHECK (saldo_actual >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presupuestos');
    }
};
