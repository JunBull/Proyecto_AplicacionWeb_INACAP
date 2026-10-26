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
        Schema::create('adjudicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')->unique()->constrained('cotizaciones')->restrictOnDelete();
            $table->foreignId('adjudicada_por')->constrained('usuarios')->restrictOnDelete();
            $table->boolean('excepcion_autorizada')->default(false);
            $table->text('justificacion')->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE adjudicaciones ADD CONSTRAINT chk_adjudicaciones_justificacion CHECK (excepcion_autorizada = false OR (excepcion_autorizada = true AND justificacion IS NOT NULL))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adjudicaciones');
    }
};
