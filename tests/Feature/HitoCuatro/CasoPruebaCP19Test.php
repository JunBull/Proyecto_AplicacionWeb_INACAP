<?php

namespace Tests\Feature\HitoCuatro;

use App\Models\Carrera;
use App\Models\Equipo;
use App\Models\Insumo;
use App\Models\SolicitudMaterial;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\PrestamoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CasoPruebaCP19Test extends TestCase
{
    use RefreshDatabase;

    protected Carrera $carrera;

    protected Ubicacion $ubicacion;

    protected User $docente;

    protected User $panolero;

    protected Equipo $equipo;

    protected Insumo $insumoConsumible;

    protected Insumo $insumoRetornable;

    protected PrestamoService $prestamoService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prestamoService = app(PrestamoService::class);

        $this->carrera = Carrera::create([
            'codigo' => 'INF-05',
            'nombre' => 'Ingeniería en Informática',
            'activa' => true,
        ]);

        $this->ubicacion = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Laboratorio de Redes y Telecomunicaciones',
            'tipo' => 'laboratorio',
        ]);

        $this->docente = User::factory()->create([
            'name' => 'Profesor Carlos Valenzuela',
            'email' => 'carlos.valenzuela@inacap.cl',
            'rol' => 'docente',
        ]);

        $this->panolero = User::factory()->create([
            'name' => 'Encargado Mario Morales',
            'email' => 'mario.morales@inacap.cl',
            'rol' => 'encargado_panol',
        ]);

        $this->equipo = Equipo::create([
            'codigo_barras' => 'EQ-RED-SW24-001',
            'nombre' => 'Switch Administrable Cisco 24 Puertos',
            'marca' => 'Cisco Systems',
            'modelo' => 'Catalyst 2960-X',
            'numero_serie' => 'SN-CISCO-889900',
            'estado' => 'disponible',
            'criticidad' => 'alta',
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        $this->insumoConsumible = Insumo::create([
            'codigo_barras' => 'INS-CON-RJ45-100',
            'nombre' => 'Conector RJ45 Cat6',
            'unidad' => 'unidad',
            'es_retornable' => false,
            'stock_actual' => 50,
            'stock_minimo' => 10,
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        $this->insumoRetornable = Insumo::create([
            'codigo_barras' => 'INS-RET-CRIMP-01',
            'nombre' => 'Crimpadora Profesional RJ45/RJ11',
            'unidad' => 'unidad',
            'es_retornable' => true,
            'stock_actual' => 10,
            'stock_minimo' => 2,
            'ubicacion_id' => $this->ubicacion->id,
        ]);
    }

    /**
     * CP-19: Consultar disponibilidad en tiempo real de insumos y equipos desde computador o móvil.
     */
    public function test_docente_consulta_disponibilidad_en_tiempo_real(): void
    {
        $disponibilidad = $this->prestamoService->consultarDisponibilidad('Cisco');

        $this->assertCount(1, $disponibilidad['equipos']);
        $this->assertEquals($this->equipo->id, $disponibilidad['equipos']->first()->id);
        $this->assertEquals('disponible', $disponibilidad['equipos']->first()->estado);

        $disponibilidadInsumos = $this->prestamoService->consultarDisponibilidad('RJ45');
        $this->assertCount(2, $disponibilidadInsumos['insumos']);
    }

    /**
     * CP-19: Solicitar materiales anticipadamente capturando solicitante, ítems, cantidades y fecha requerida.
     */
    public function test_docente_registra_solicitud_anticipada_correctamente(): void
    {
        $fechaRequerida = Carbon::tomorrow()->toDateString();

        $solicitud = $this->prestamoService->crearSolicitud(
            $this->docente,
            [
                'fecha_requerida' => $fechaRequerida,
                'observaciones' => 'Práctica de Laboratorio de Redes - Sección 001D',
            ],
            [
                [
                    'equipo_id' => $this->equipo->id,
                    'cantidad' => 1,
                ],
                [
                    'insumo_id' => $this->insumoConsumible->id,
                    'cantidad' => 12,
                ],
                [
                    'insumo_id' => $this->insumoRetornable->id,
                    'cantidad' => 3,
                ],
            ]
        );

        $this->assertInstanceOf(SolicitudMaterial::class, $solicitud);
        $this->assertEquals($this->docente->id, $solicitud->solicitante_id);
        $this->assertEquals('pendiente', $solicitud->estado);
        $this->assertEquals($fechaRequerida, $solicitud->fecha_requerida->toDateString());
        $this->assertCount(3, $solicitud->detalles);

        // Verificación de visualización inmediata en cola de pañolero
        $solicitudesEnCola = SolicitudMaterial::where('estado', 'pendiente')->get();
        $this->assertTrue($solicitudesEnCola->contains('id', $solicitud->id));
    }

    /**
     * CP-19: Validación de fecha requerida anterior al día de hoy.
     */
    public function test_solicitud_con_fecha_anterior_es_rechazada(): void
    {
        $this->expectException(ValidationException::class);

        $this->prestamoService->crearSolicitud(
            $this->docente,
            [
                'fecha_requerida' => Carbon::yesterday()->toDateString(),
                'observaciones' => 'Intento inválido en fecha pasada',
            ],
            [
                [
                    'equipo_id' => $this->equipo->id,
                    'cantidad' => 1,
                ],
            ]
        );
    }

    /**
     * CP-19: Validación de restricción CHECK en detalle_solicitud (mutuamente excluyente insumo vs equipo).
     */
    public function test_solicitud_con_item_invalido_ambos_o_ninguno_es_rechazada(): void
    {
        $this->expectException(ValidationException::class);

        $this->prestamoService->crearSolicitud(
            $this->docente,
            [
                'fecha_requerida' => Carbon::tomorrow()->toDateString(),
            ],
            [
                [
                    'equipo_id' => $this->equipo->id,
                    'insumo_id' => $this->insumoConsumible->id, // Violación de exclusividad
                    'cantidad' => 1,
                ],
            ]
        );
    }

    /**
     * CP-19: Interfaz reactiva Volt responsiva para docentes.
     */
    public function test_pantalla_docente_volt_permite_solicitar_reactivamente(): void
    {
        $this->actingAs($this->docente);

        Volt::test('pages.solicitudes.index')
            ->set('fecha_requerida', Carbon::tomorrow()->toDateString())
            ->set('observaciones', 'Uso en taller móvil')
            ->call('agregarItem', 'equipo', $this->equipo->id)
            ->call('agregarItem', 'insumo', $this->insumoConsumible->id)
            ->call('registrarSolicitud')
            ->assertHasNoErrors()
            ->assertSee('¡Solicitud registrada exitosamente!');

        $this->assertDatabaseHas('solicitudes_material', [
            'solicitante_id' => $this->docente->id,
            'estado' => 'pendiente',
        ]);
    }

    /**
     * CP-19: Docente agrega ítems con +Pedir (toggle) y los preserva al abrir el modal de solicitud.
     */
    public function test_docente_preserva_items_al_abrir_modal_crear(): void
    {
        $this->actingAs($this->docente);

        Volt::test('pages.solicitudes.index')
            ->call('toggleItem', 'equipo', $this->equipo->id)
            ->assertSet('itemsSeleccionados', function ($items) {
                return count($items) === 1 && $items[0]['id'] === $this->equipo->id;
            })
            ->assertSee('✓ En Solicitud')
            ->assertSee('1 material en canasta')
            ->call('abrirModalCrear')
            ->assertSet('mostrarModalCrear', true)
            ->assertSet('itemsSeleccionados', function ($items) {
                return count($items) === 1 && $items[0]['id'] === $this->equipo->id;
            })
            ->assertSee('Total ítems: 1')
            ->set('fecha_requerida', Carbon::tomorrow()->toDateString())
            ->call('registrarSolicitud')
            ->assertHasNoErrors()
            ->assertSee('¡Solicitud registrada exitosamente!');

        $this->assertDatabaseHas('solicitudes_material', [
            'solicitante_id' => $this->docente->id,
            'estado' => 'pendiente',
        ]);
    }

    /**
     * CP-19: Docente puede alternar (toggle) ítems o vaciar la canasta.
     */
    public function test_docente_puede_alternar_o_vaciar_seleccion_de_materiales(): void
    {
        $this->actingAs($this->docente);

        $test = Volt::test('pages.solicitudes.index')
            ->call('toggleItem', 'equipo', $this->equipo->id)
            ->assertSet('itemsSeleccionados', function ($items) {
                return count($items) === 1;
            })
            // Segundo toggle lo remueve
            ->call('toggleItem', 'equipo', $this->equipo->id)
            ->assertSet('itemsSeleccionados', [])
            ->assertSee('+ Pedir');

        // Agregar de nuevo y probar vaciar canasta
        $test->call('toggleItem', 'insumo', $this->insumoConsumible->id)
            ->assertSet('itemsSeleccionados', function ($items) {
                return count($items) === 1;
            })
            ->call('vaciarItems')
            ->assertSet('itemsSeleccionados', [])
            ->assertSee('Se han vaciado todos los ítems de la solicitud.');
    }
}
