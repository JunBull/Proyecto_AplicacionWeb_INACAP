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
        Schema::create('insumos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_barras')->unique();
            $table->string('nombre');
            $table->string('unidad');
            $table->boolean('es_retornable')->default(false);
            $table->decimal('stock_actual', 10, 2)->default(0);
            $table->decimal('stock_minimo', 10, 2)->default(0);
            $table->foreignId('ubicacion_id')->constrained('ubicaciones')->restrictOnDelete();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE insumos ADD CONSTRAINT chk_insumos_stock_actual CHECK (stock_actual >= 0)');
            DB::statement('ALTER TABLE insumos ADD CONSTRAINT chk_insumos_stock_minimo CHECK (stock_minimo >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insumos');
    }
};
