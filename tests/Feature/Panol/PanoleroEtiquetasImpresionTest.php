<?php

namespace Tests\Feature\Panol;

use App\Models\Carrera;
use App\Models\Equipo;
use App\Models\Insumo;
use App\Models\Ubicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PanoleroEtiquetasImpresionTest extends TestCase
{
    use RefreshDatabase;

    private User $panolero;

    private Carrera $carrera;

    private Ubicacion $ubicacion;

    private Equipo $equipo;

    private Insumo $insumo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->panolero = User::factory()->create([
            'name' => 'Pañolero Etiquetas Test',
            'email' => 'panolero.etiquetas@inacap.cl',
            'rol' => 'encargado_panol',
            'activo' => true,
        ]);

        $this->carrera = Carrera::create([
            'codigo' => 'TEL-01',
            'nombre' => 'Telecomunicaciones',
            'activa' => true,
        ]);

        $this->ubicacion = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Laboratorio Telecomunicaciones',
            'tipo' => 'laboratorio',
        ]);

        $this->equipo = Equipo::create([
            'codigo_barras' => 'EQ-TEL-001',
            'nombre' => 'Router Cisco 2911',
            'marca' => 'Cisco',
            'modelo' => '2911',
            'numero_serie' => 'SN-CISCO-2911',
            'estado' => 'disponible',
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        $this->insumo = Insumo::create([
            'codigo_barras' => 'INS-CAB-CAT6',
            'nombre' => 'Cable UTP Cat6',
            'unidad' => 'unidades',
            'stock_actual' => 50,
            'stock_minimo' => 10,
            'ubicacion_id' => $this->ubicacion->id,
        ]);
    }

    public function test_vista_etiquetas_muestra_titulo_limpio_y_boton_generar_etiquetas(): void
    {
        $this->actingAs($this->panolero);

        $component = Volt::test('pages.etiquetas.index');

        // Título simplificado
        $component->assertSee('Etiquetas');
        $component->assertSee('Generar Etiquetas');

        // Subtítulos académicos y técnicos removidos de la vista principal
        $component->assertDontSee('Generación de códigos lineales 1D estandarizados');
        $component->assertDontSee('Renderizado de alta fidelidad cumpliendo SLA-07');
        $component->assertDontSee('Generar Lote Seleccionado');
    }

    public function test_metricas_kpi_estan_contenidas_en_acordeon_colapsable_no_print(): void
    {
        $this->actingAs($this->panolero);

        $component = Volt::test('pages.etiquetas.index');

        // Las métricas existen para compatibilidad con CP-27 y auditoría, pero están en details no-print
        $component->assertSee('Métricas de Cobertura y Rendimiento');
        $component->assertSee('Cobertura de Etiquetado (KPI-05)');
        $component->assertSee('Pendientes de Impresión');
        $component->assertSee('Rendimiento de Lotes (SLA-07)');
        $component->assertSee('no-print');
    }

    public function test_generar_lote_etiquetas_muestra_contenedor_impresion_sin_banner_sla(): void
    {
        $this->actingAs($this->panolero);

        $component = Volt::test('pages.etiquetas.index')
            ->set('seleccionadosEquipos', [$this->equipo->id])
            ->set('seleccionadosInsumos', [$this->insumo->id])
            ->call('generarLote');

        // Verifica que se generó el lote con el contenedor específico para imprimir
        $component->assertSee('id="seccion-impresion-etiquetas"', false);
        $component->assertSee('Imprimir Etiquetas');
        $component->assertSee('window.print()', false);
        $component->assertSee('EQ-TEL-001');
        $component->assertSee('INS-CAB-CAT6');

        // Verifica que el banner azul ruidoso de SLA-07 fue eliminado
        $component->assertDontSee('Métrica SLA-07: Lote de');
    }

    public function test_estilos_media_print_configurados_para_imprimir_solo_etiquetas(): void
    {
        $this->actingAs($this->panolero);

        $response = $this->get(route('panol.etiquetas'));
        $response->assertOk();

        // Reglas de CSS @media print requeridas
        $response->assertSee('@media print', false);
        $response->assertSee('#seccion-impresion-etiquetas', false);
        $response->assertSee('visibility: hidden !important', false);
        $response->assertSee('visibility: visible !important', false);
    }
}
