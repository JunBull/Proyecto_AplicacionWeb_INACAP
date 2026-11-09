<?php

namespace Tests\Feature\Panol;

use App\Models\Carrera;
use App\Models\Insumo;
use App\Models\Ubicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PanoleroInventarioSimplificadoTest extends TestCase
{
    use RefreshDatabase;

    private User $panolero;

    private Carrera $carrera;

    private Ubicacion $ubicacion;

    private Insumo $insumo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->panolero = User::factory()->create([
            'name' => 'Pañolero Inventario Test',
            'email' => 'panolero.inventario@inacap.cl',
            'rol' => 'encargado_panol',
            'activo' => true,
        ]);

        $this->carrera = Carrera::create([
            'codigo' => 'INF-01',
            'nombre' => 'Ingeniería en Informática',
            'activa' => true,
        ]);

        $this->ubicacion = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Bodega Central Informática',
            'tipo' => 'panol',
        ]);

        $this->insumo = Insumo::create([
            'codigo_barras' => 'INS-TEST-001',
            'nombre' => 'Conector RJ45 Macho Cat6',
            'unidad' => 'unidad',
            'stock_actual' => 100,
            'stock_minimo' => 20,
            'ubicacion_id' => $this->ubicacion->id,
        ]);
    }

    public function test_tabla_inventario_solo_muestra_boton_ajuste_y_remueve_ingreso_salida(): void
    {
        $this->actingAs($this->panolero);

        $component = Volt::test('pages.inventario.index');

        // Botón Ajuste presente
        $component->assertSee('Ajuste');

        // Botones directos removidos de la tabla
        $component->assertDontSee('+ Ingreso');
        $component->assertDontSee('- Salida');
    }

    public function test_encabezados_y_subtitulos_inventario_sin_codigos_auditoria(): void
    {
        $this->actingAs($this->panolero);

        $component = Volt::test('pages.inventario.index');

        // Subtítulo limpio
        $component->assertSee('Control de existencias, ubicaciones físicas y alertas de stock crítico.');
        $component->assertDontSee('transacciones ACID y monitor de stock crítico con cotización rápida');

        // Cabecera limpia
        $component->assertSee('Ubicación Física');
        $component->assertDontSee('Ubicación Granular (CP-11)');

        // Modales sin etiquetas técnicas
        $component->assertDontSee('(Umbral KPI-04)');
        $component->assertDontSee('(CP-13)');
    }
}
