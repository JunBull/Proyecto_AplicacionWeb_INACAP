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
        Schema::create('compra_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->foreignId('insumo_id')->nullable()->constrained('insumos')->restrictOnDelete();
            $table->string('descripcion');
            $table->decimal('cantidad', 10, 2);
            $table->decimal('costo_unitario', 14, 2);
            $table->decimal('impuesto', 14, 2)->default(0);
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE compra_detalles ADD CONSTRAINT chk_compra_detalles_cantidad CHECK (cantidad > 0)');
            DB::statement('ALTER TABLE compra_detalles ADD CONSTRAINT chk_compra_detalles_costo CHECK (costo_unitario >= 0)');
            DB::statement('ALTER TABLE compra_detalles ADD CONSTRAINT chk_compra_detalles_impuesto CHECK (impuesto >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compra_detalles');
    }
};
