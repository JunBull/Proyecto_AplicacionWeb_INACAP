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
        Schema::table('fallas', function (Blueprint $table) {
            $table->string('tipo_destino', 20)->nullable()->after('ubicacion_id');
            $table->string('edificio_destino', 50)->nullable()->after('tipo_destino');
            $table->string('espacio_destino', 100)->nullable()->after('edificio_destino');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fallas', function (Blueprint $table) {
            $table->dropColumn([
                'tipo_destino',
                'edificio_destino',
                'espacio_destino',
            ]);
        });
    }
};
