<?php

namespace Tests\Feature\HitoSiete;

use App\Models\Carrera;
use App\Models\Equipo;
use App\Models\Insumo;
use App\Models\Ubicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CasoPruebaCP27Test extends TestCase
{
    use RefreshDatabase;

    protected User $panolero;

    protected Carrera $carrera;

    protected Ubicacion $bodega;

    protected function setUp(): void
    {
        parent::setUp();

        $this->panolero = User::factory()->create([
            'name' => 'Pañolero Etiquetas',
            'email' => 'etiquetas.panol@inacap.cl',
            'rol' => 'encargado_panol',
            'activo' => true,
        ]);

        $this->carrera = Carrera::create([
            'codigo' => 'INF-01',
            'nombre' => 'Ingeniería en Informática',
            'activa' => true,
        ]);

        $this->bodega = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Bodega Central de Informática',
            'tipo' => 'panol',
        ]);
    }

    /**
     * CP-27 (actualizado): El módulo de Etiquetas opera sin el acordeón de Métricas de Cobertura y Rendimiento.
     */
    public function test_etiquetas_despliega_vista_operativa_sin_panel_metricas(): void
    {
        $this->actingAs($this->panolero);

        Equipo::create([
            'codigo_barras' => 'EQ-INF-ETQ-001',
            'nombre' => 'Notebook HP ProBook 450 G8',
            'marca' => 'HP',
            'modelo' => 'ProBook 450 G8',
            'numero_serie' => 'SN-HP-001',
            'estado' => 'disponible',
            'ubicacion_id' => $this->bodega->id,
        ]);

        Insumo::create([
            'codigo_barras' => 'INS-INF-ETQ-001',
            'nombre' => 'Tester Cable de Red RJ45 / RJ11',
            'unidad' => 'unidad',
            'es_retornable' => true,
            'stock_actual' => 5.0,
            'stock_minimo' => 2.0,
            'ubicacion_id' => $this->bodega->id,
        ]);

        Volt::test('pages.etiquetas.index')
            ->assertOk()
            ->assertSee('Etiquetas')
            ->assertSee('EQ-INF-ETQ-001')
            ->assertSee('INS-INF-ETQ-001')
            ->assertDontSee('Métricas de Cobertura y Rendimiento')
            ->assertDontSee('Cobertura de Etiquetado (KPI-05)');
    }

    /**
     * CP-27 & SLA-07: Generación de lote de etiquetas ejecuta en tiempo óptimo (meta <= 10.0 segundos).
     */
    public function test_generacion_lote_etiquetas_cumple_sla_07(): void
    {
        $this->actingAs($this->panolero);

        $equipo = Equipo::create([
            'codigo_barras' => 'EQ-LOTE-SLA-001',
            'nombre' => 'Osciloscopio Digital Tektronix',
            'marca' => 'Tektronix',
            'modelo' => 'TBS1052B',
            'numero_serie' => 'SN-TEK-001',
            'estado' => 'disponible',
            'ubicacion_id' => $this->bodega->id,
        ]);

        $insumo = Insumo::create([
            'codigo_barras' => 'INS-LOTE-SLA-002',
            'nombre' => 'Cautín Regulable 60W',
            'unidad' => 'unidad',
            'es_retornable' => true,
            'stock_actual' => 10.0,
            'stock_minimo' => 3.0,
            'ubicacion_id' => $this->bodega->id,
        ]);

        $component = Volt::test('pages.etiquetas.index')
            ->set('seleccionadosEquipos', [$equipo->id])
            ->set('seleccionadosInsumos', [$insumo->id])
            ->call('generarLote')
            ->assertSee('EQ-LOTE-SLA-001')
            ->assertSee('INS-LOTE-SLA-002');

        $this->assertEquals(2, $component->get('totalGeneradas'));
        $tiempoMs = $component->get('tiempoGeneracionMs');
        $this->assertNotNull($tiempoMs);
        $this->assertLessThanOrEqual(10000, $tiempoMs, 'El lote debe generarse en <= 10.0 segundos (SLA-07).');
    }
}
