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
     * CP-27: Consulta y despliegue de métricas de cobertura de etiquetado (KPI-05) y estadísticas en módulo de Etiquetas.
     */
    public function test_despliega_metricas_cobertura_etiquetado_kpi_05(): void
    {
        $this->actingAs($this->panolero);

        // Crear 2 equipos con código de barras
        $eq1 = Equipo::create([
            'codigo_barras' => 'EQ-INF-ETQ-001',
            'nombre' => 'Notebook HP ProBook 450 G8',
            'marca' => 'HP',
            'modelo' => 'ProBook 450 G8',
            'numero_serie' => 'SN-HP-001',
            'estado' => 'disponible',
            'ubicacion_id' => $this->bodega->id,
        ]);

        $eq2 = Equipo::create([
            'codigo_barras' => 'EQ-INF-ETQ-002',
            'nombre' => 'Switch Administrable Cisco Catalyst 2960',
            'marca' => 'Cisco',
            'modelo' => 'Catalyst 2960',
            'numero_serie' => 'SN-CSCO-002',
            'estado' => 'disponible',
            'ubicacion_id' => $this->bodega->id,
        ]);

        // Crear 1 insumo con código de barras
        $ins1 = Insumo::create([
            'codigo_barras' => 'INS-INF-ETQ-001',
            'nombre' => 'Tester Cable de Red RJ45 / RJ11',
            'unidad' => 'unidad',
            'es_retornable' => true,
            'stock_actual' => 5.0,
            'stock_minimo' => 2.0,
            'ubicacion_id' => $this->bodega->id,
        ]);

        // Total catálogo = 3 items, los 3 con código => Cobertura 100.0%
        $component = Volt::test('pages.etiquetas.index')
            ->assertOk()
            ->assertSee('Cobertura de Etiquetado (KPI-05)')
            ->assertSee('Pendientes de Impresión')
            ->assertSee('Rendimiento de Lotes (SLA-07)')
            ->assertSee('100%');

        $metricas = $component->instance()->metricasEtiquetas();
        $this->assertEquals(3, $metricas['total_items']);
        $this->assertEquals(3, $metricas['total_con_codigo']);
        $this->assertEquals(100.0, $metricas['porcentaje_cobertura']);
        $this->assertEquals(3, $metricas['pendientes_impresion']);
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
