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
        Schema::table('mantenimientos', function (Blueprint $table) {
            $table->string('subtipo', 20)->default('REV-A')->after('tipo');
        });

        Schema::table('equipos', function (Blueprint $table) {
            $table->boolean('requiere_calibracion')->default(false)->after('criticidad');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipos', function (Blueprint $table) {
            $table->dropColumn('requiere_calibracion');
        });

        Schema::table('mantenimientos', function (Blueprint $table) {
            $table->dropColumn('subtipo');
        });
    }
};
