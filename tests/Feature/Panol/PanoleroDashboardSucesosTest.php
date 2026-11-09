<?php

namespace Tests\Feature\Panol;

use App\Models\Carrera;
use App\Models\DetalleSolicitud;
use App\Models\Entrega;
use App\Models\Insumo;
use App\Models\SolicitudMaterial;
use App\Models\Ubicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanoleroDashboardSucesosTest extends TestCase
{
    use RefreshDatabase;

    private User $panolero;

    private Carrera $carrera;

    private Ubicacion $ubicacion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->panolero = User::factory()->create([
            'name' => 'Pañolero Dashboard Test',
            'email' => 'panolero.dash@inacap.cl',
            'rol' => 'encargado_panol',
            'activo' => true,
        ]);

        $this->carrera = Carrera::create([
            'codigo' => 'INF-DASH',
            'nombre' => 'Ingeniería en Informática',
            'activa' => true,
        ]);

        $this->ubicacion = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Pañol Central Informática',
            'tipo' => 'panol',
        ]);
    }

    public function test_dashboard_panolero_no_muestra_header_redundante_y_muestra_modulos(): void
    {
        $this->actingAs($this->panolero);

        $response = $this->get(route('dashboard'));
        $response->assertOk();

        // No debe mostrar el header superior removido ni la píldora de rol
        $response->assertDontSee('Panel Principal de Pañol');
        $response->assertDontSee('Rol: Encargado pañol');

        // Debe mostrar títulos limpios
        $response->assertSee('Módulos');
        $response->assertSee('Sucesos Importantes');
        $response->assertSee('Despacho y Devoluciones');
        $response->assertSee('Inventario de Consumibles');
    }

    public function test_dashboard_despliega_insumos_en_stock_critico_con_boton_cotizar(): void
    {
        $this->actingAs($this->panolero);

        // Crear insumo con stock crítico (stock_actual <= stock_minimo)
        $insumoCritico = Insumo::create([
            'codigo_barras' => 'INS-CRIT-DASH',
            'nombre' => 'Tester Cable Red RJ45',
            'unidad' => 'unidad',
            'stock_actual' => 1,
            'stock_minimo' => 5,
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        $response = $this->get(route('dashboard'));
        $response->assertOk();

        $response->assertSee('Stock Crítico');
        $response->assertSee('Tester Cable Red RJ45');
        $response->assertSee('INS-CRIT-DASH');
        $response->assertSee('Cotizar');
        $response->assertSee(route('panol.cotizaciones', ['insumo_id' => $insumoCritico->id]));
    }

    public function test_dashboard_despliega_prestamos_vencidos_con_boton_devolver(): void
    {
        $this->actingAs($this->panolero);

        $docente = User::factory()->create([
            'name' => 'Profesor Juan Pérez',
            'rol' => 'docente',
            'activo' => true,
        ]);

        $insumo = Insumo::create([
            'codigo_barras' => 'INS-DASH-RET',
            'nombre' => 'Multímetro Digital Pro',
            'unidad' => 'unidad',
            'es_retornable' => true,
            'stock_actual' => 10,
            'stock_minimo' => 2,
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        $solicitud = SolicitudMaterial::create([
            'solicitante_id' => $docente->id,
            'fecha_requerida' => Carbon::yesterday()->toDateString(),
            'estado' => 'entregada_abierta',
            'tipo' => 'clase',
        ]);

        DetalleSolicitud::create([
            'solicitud_id' => $solicitud->id,
            'insumo_id' => $insumo->id,
            'cantidad_solicitada' => 1,
            'cantidad_entregada' => 1,
            'cantidad_devuelta' => 0,
        ]);

        Entrega::create([
            'solicitud_id' => $solicitud->id,
            'entregado_por' => $this->panolero->id,
            'receptor_nombre' => 'Profesor Juan Pérez',
            'receptor_tipo' => 'docente',
            'fecha_entrega' => Carbon::yesterday(),
            'hora_devolucion_acordada' => '18:00:00',
        ]);

        $response = $this->get(route('dashboard'));
        $response->assertOk();

        $response->assertSee('Préstamos Vencidos');
        $response->assertSee('Profesor Juan Pérez');
        $response->assertSee('Atrasado');
        $response->assertSee('Devolver');
        $response->assertSee(route('panol.devoluciones'));
    }

    public function test_dashboard_muestra_estado_al_dia_cuando_no_hay_alertas(): void
    {
        $this->actingAs($this->panolero);

        // Sin insumos críticos ni entregas vencidas
        $response = $this->get(route('dashboard'));
        $response->assertOk();

        $response->assertSee('Al día');
        $response->assertSee('¡Operación al día!');
        $response->assertSee('No hay alertas de stock ni préstamos con retraso.');
    }
}
