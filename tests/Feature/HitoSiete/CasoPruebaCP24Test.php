<?php

namespace Tests\Feature\HitoSiete;

use App\Models\Carrera;
use App\Models\Insumo;
use App\Models\Ubicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CasoPruebaCP24Test extends TestCase
{
    use RefreshDatabase;

    protected User $panolero;

    protected Carrera $carrera;

    protected Ubicacion $ubicacion;

    protected Insumo $insumoCritico;

    protected Insumo $insumoNormal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->panolero = User::factory()->create([
            'name' => 'Pañolero Pedro',
            'email' => 'pedro.panolero@inacap.cl',
            'rol' => 'encargado_panol',
            'activo' => true,
        ]);

        $this->carrera = Carrera::create([
            'codigo' => 'TEL-01',
            'nombre' => 'Telecomunicaciones y Redes',
            'activa' => true,
        ]);

        $this->ubicacion = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Bodega Central de Redes',
            'tipo' => 'panol',
        ]);

        // Insumo en stock crítico (stock_actual <= stock_minimo)
        $this->insumoCritico = Insumo::create([
            'codigo_barras' => 'INS-CRIT-001',
            'nombre' => 'Conector RJ-45 Blindado Cat 6A',
            'unidad' => 'unidad',
            'es_retornable' => false,
            'stock_actual' => 3.0,
            'stock_minimo' => 15.0,
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        // Insumo con holgura de stock
        $this->insumoNormal = Insumo::create([
            'codigo_barras' => 'INS-NORM-002',
            'nombre' => 'Bobina Cable UTP Cat 6 305m',
            'unidad' => 'rollo',
            'es_retornable' => false,
            'stock_actual' => 8.0,
            'stock_minimo' => 2.0,
            'ubicacion_id' => $this->ubicacion->id,
        ]);
    }

    /**
     * CP-24: El dashboard de inventario lista únicamente los insumos con stock crítico (KPI-04).
     * El pañolero ve el aviso pero no tiene botón de cotizar.
     */
    public function test_dashboard_inventario_despliega_productos_criticos(): void
    {
        $this->actingAs($this->panolero);

        Volt::test('pages.inventario.index')
            ->assertSee('Inventario y Mantención de Equipo')
            ->assertSee('Alertas de Stock Crítico (KPI-04 / CP-24)')
            ->assertSee($this->insumoCritico->nombre)
            ->assertSee($this->insumoCritico->codigo_barras)
            ->assertSee('Requiere Reposición')
            ->assertDontSee('Cotizar');
    }

    /**
     * CP-24: El administrador sí visualiza el botón "Cotizar" en el dashboard de inventario.
     */
    public function test_administrador_ve_boton_cotizar_en_dashboard_inventario(): void
    {
        $admin = User::factory()->create([
            'rol' => 'administrador',
            'activo' => true,
        ]);

        $this->actingAs($admin);

        Volt::test('pages.inventario.index')
            ->assertSee('Alertas de Stock Crítico (KPI-04 / CP-24)')
            ->assertSee('Cotizar');
    }

    /**
     * CP-24: El botón "Cotizar" precarga automáticamente el insumo y su carrera en la solicitud de cotización del administrador.
     */
    public function test_boton_cotizar_precarga_insumo_en_solicitudes_cotizacion(): void
    {
        $admin = User::factory()->create([
            'rol' => 'administrador',
            'activo' => true,
        ]);

        $this->actingAs($admin);

        // Simular que el administrador llega a cotizaciones desde el botón "Cotizar" del dashboard
        $this->get(route('admin.cotizaciones', ['insumo_id' => $this->insumoCritico->id]))
            ->assertOk();

        // Verificar reactividad en el componente Volt de cotizaciones
        $component = Volt::test('pages.cotizaciones.index', ['insumo_id' => $this->insumoCritico->id]);

        $this->assertTrue($component->get('mostrarModalSolicitud'));
        $this->assertEquals($this->carrera->id, $component->get('carrera_id'));
        $this->assertStringContainsString($this->insumoCritico->nombre, $component->get('descripcion'));
        $this->assertStringContainsString($this->insumoCritico->codigo_barras, $component->get('descripcion'));
    }

    /**
     * El pañolero no tiene permisos para acceder a cotizaciones en el servidor.
     */
    public function test_panolero_no_puede_acceder_a_cotizaciones(): void
    {
        $this->actingAs($this->panolero);

        $this->get(route('admin.cotizaciones'))
            ->assertStatus(403);
    }
}
