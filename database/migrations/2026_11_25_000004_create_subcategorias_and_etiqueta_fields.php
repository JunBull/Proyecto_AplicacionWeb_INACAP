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
        if (! Schema::hasTable('subcategorias')) {
            Schema::create('subcategorias', function (Blueprint $table) {
                $table->id();
                $table->foreignId('categoria_id')->constrained('categorias')->cascadeOnDelete();
                $table->string('nombre', 120);
                $table->string('slug', 120)->unique();
                $table->string('codigo_prefijo', 20)->nullable();
                $table->integer('orden')->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('equipos')) {
            Schema::table('equipos', function (Blueprint $table) {
                if (! Schema::hasColumn('equipos', 'subcategoria_id')) {
                    $table->foreignId('subcategoria_id')
                        ->nullable()
                        ->after('categoria_id')
                        ->constrained('subcategorias')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('equipos', 'etiqueta_impresa')) {
                    $table->boolean('etiqueta_impresa')
                        ->default(false)
                        ->after('reservado_clases');
                }

                if (! Schema::hasColumn('equipos', 'etiqueta_impresa_en')) {
                    $table->timestamp('etiqueta_impresa_en')
                        ->nullable()
                        ->after('etiqueta_impresa');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('equipos')) {
            Schema::table('equipos', function (Blueprint $table) {
                if (Schema::hasColumn('equipos', 'subcategoria_id')) {
                    $table->dropConstrainedForeignId('subcategoria_id');
                }

                $cols = [];
                if (Schema::hasColumn('equipos', 'etiqueta_impresa')) {
                    $cols[] = 'etiqueta_impresa';
                }
                if (Schema::hasColumn('equipos', 'etiqueta_impresa_en')) {
                    $cols[] = 'etiqueta_impresa_en';
                }
                if (! empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }

        Schema::dropIfExists('subcategorias');
    }
};
