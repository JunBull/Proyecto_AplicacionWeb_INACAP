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
        if (! Schema::hasTable('categorias')) {
            Schema::create('categorias', function (Blueprint $table) {
                $table->id();
                $table->string('nombre');
                $table->string('slug')->unique();
                $table->string('descripcion')->nullable();
                $table->string('icono')->nullable();
                $table->integer('orden')->default(0);
                $table->enum('criticidad_sugerida', ['alta', 'media', 'baja'])->default('media');
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('equipos') && ! Schema::hasColumn('equipos', 'categoria_id')) {
            Schema::table('equipos', function (Blueprint $table) {
                $table->foreignId('categoria_id')
                    ->nullable()
                    ->after('ubicacion_id')
                    ->constrained('categorias')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('equipos') && Schema::hasColumn('equipos', 'categoria_id')) {
            Schema::table('equipos', function (Blueprint $table) {
                $table->dropConstrainedForeignId('categoria_id');
            });
        }

        Schema::dropIfExists('categorias');
    }
};
