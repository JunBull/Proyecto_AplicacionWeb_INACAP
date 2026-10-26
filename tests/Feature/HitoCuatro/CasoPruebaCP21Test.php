<?php

namespace Tests\Feature\HitoCuatro;

use App\Models\Carrera;
use App\Models\DetalleSolicitud;
use App\Models\Devolucion;
use App\Models\Entrega;
use App\Models\Equipo;
use App\Models\Falla;
use App\Models\Insumo;
use App\Models\MovimientoInventario;
use App\Models\SolicitudMaterial;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\PrestamoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CasoPruebaCP21Test extends TestCase
{
    use RefreshDatabase;

    protected Carrera $carrera;
    protected Ubicacion $ubicacion;
    protected User $docente;
    protected User $panolero;
    protected Equipo $equipoOsciloscopio;
    protected Insumo $insumoSonda;
    protected PrestamoService $prestamoService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prestamoService = app(PrestamoService::class);

        $this->carrera = Carrera::create([
            'codigo' => 'ELE-01',
            'nombre' => 'Ingeniería en Electricidad y Automatización',
            'activa' => true,
        ]);

        $this->ubicacion = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Laboratorio de Instrumentación Electrónica',
            'tipo' => 'laboratorio',
        ]);

        $this->docente = User::factory()->create([
            'name' => 'Profesor Hernán Rojas',
            'email' => 'hernan.rojas@inacap.cl',
            'rol' => 'docente',
        ]);

        $this->panolero = User::factory()->create([
            'name' => 'Encargado Felipe Bustos',
            'email' => 'felipe.bustos@inacap.cl',
            'rol' => 'encargado_panol',
        ]);

        $this->equipoOsciloscopio = Equipo::create([
            'codigo_barras' => 'EQ-ELE-OSC-002',
            'nombre' => 'Osciloscopio Digital Tektronix 100MHz',
            'marca' => 'Tektronix',
            'modelo' => 'TBS1102B',
            'numero_serie' => 'TEK-2026-4433',
            'estado' => 'disponible',
            'criticidad' => 'alta',
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        $this->insumoSonda = Insumo::create([
            'codigo_barras' => 'INS-RET-SONDA-10X',
            'nombre' => 'Sonda de Medición Atenuada 10X',
            'unidad' => 'unidad',
            'es_retornable' => true,
            'stock_actual' => 10.0,
            'stock_minimo' => 2.0,
            'ubicacion_id' => $this->ubicacion->id,
        ]);
    }

    /**
     * CP-21: Devolución completa en buen estado (conforme).
     */
    public function test_devolucion_completa_en_buen_estado_cierra_prestamo_y_restaura_stock(): void
    {
        $solicitud = $this->prestamoService->crearSolicitud(
            $this->docente,
            ['fecha_requerida' => Carbon::tomorrow()->toDateString()],
            [
                ['equipo_id' => $this->equipoOsciloscopio->id, 'cantidad' => 1.0],
                ['insumo_id' => $this->insumoSonda->id, 'cantidad' => 2.0],
            ]
        );

        $entrega = $this->prestamoService->entregarMateriales(
            $solicitud,
            $this->panolero,
            'Profesor Hernán Rojas',
            'docente'
        );

        $detEquipo = $solicitud->detalles->firstWhere('equipo_id', $this->equipoOsciloscopio->id);
        $detInsumo = $solicitud->detalles->firstWhere('insumo_id', $this->insumoSonda->id);

        // Devolución completa en buen estado
        $devolucion = $this->prestamoService->registrarDevolucion(
            $entrega,
            $this->panolero,
            [
                ['detalle_id' => $detEquipo->id, 'conforme' => true],
                ['detalle_id' => $detInsumo->id, 'cantidad' => 2.0, 'conforme' => true],
            ],
            'Todo devuelto en perfecto estado'
        );

        $this->assertInstanceOf(Devolucion::class, $devolucion);
        $this->assertTrue($devolucion->conforme);

        // Equipo vuelve a disponible
        $this->assertEquals('disponible', $this->equipoOsciloscopio->fresh()->estado);

        // Stock del insumo retornado se restaura a 10.0
        $this->assertEquals(10.0, (float) $this->insumoSonda->fresh()->stock_actual);

        // Kardex registra reingreso_devolucion
        $movReingreso = MovimientoInventario::where('solicitud_id', $solicitud->id)
            ->where('tipo', 'reingreso_devolucion')
            ->first();
        $this->assertNotNull($movReingreso);
        $this->assertEquals(2.0, (float) $movReingreso->cantidad);

        // Solicitud se cierra completamente
        $this->assertEquals('cerrada', $solicitud->fresh()->estado);
    }

    /**
     * CP-21: Devolución parcial de materiales retornables.
     */
    public function test_devolucion_parcial_mantiene_prestamo_abierto(): void
    {
        $solicitud = $this->prestamoService->crearSolicitud(
            $this->docente,
            ['fecha_requerida' => Carbon::tomorrow()->toDateString()],
            [
                ['insumo_id' => $this->insumoSonda->id, 'cantidad' => 4.0],
            ]
        );

        $entrega = $this->prestamoService->entregarMateriales(
            $solicitud,
            $this->panolero,
            'Alumno Taller',
            'estudiante'
        );

        $detalle = $solicitud->detalles->first();

        // Se devuelven solo 2 de las 4 sondas entregadas
        $this->prestamoService->registrarDevolucion(
            $entrega,
            $this->panolero,
            [
                ['detalle_id' => $detalle->id, 'cantidad' => 2.0, 'conforme' => true],
            ],
            'Devolución parcial de 2 sondas; quedan 2 en taller'
        );

        $detalleActualizado = $detalle->fresh();
        $this->assertEquals(2.0, (float) $detalleActualizado->cantidad_devuelta);
        $this->assertEquals('parcial', $detalleActualizado->estado_devolucion);

        // El préstamo debe permanecer abierto
        $this->assertEquals('entregada_abierta', $solicitud->fresh()->estado);
    }

    /**
     * CP-21: Devolución con daño físico de un equipo (registro de incidencia, cambio a no_disponible).
     */
    public function test_devolucion_con_dano_registra_falla_y_pasa_equipo_a_no_disponible(): void
    {
        $solicitud = $this->prestamoService->crearSolicitud(
            $this->docente,
            ['fecha_requerida' => Carbon::tomorrow()->toDateString()],
            [
                ['equipo_id' => $this->equipoOsciloscopio->id, 'cantidad' => 1.0],
            ]
        );

        $entrega = $this->prestamoService->entregarMateriales(
            $solicitud,
            $this->panolero,
            'Estudiante Laboratorio',
            'estudiante'
        );

        $detalle = $solicitud->detalles->first();

        // Se devuelve el osciloscopio con la pantalla quebrada
        $devolucion = $this->prestamoService->registrarDevolucion(
            $entrega,
            $this->panolero,
            [
                [
                    'detalle_id' => $detalle->id,
                    'conforme' => false,
                    'descripcion_dano' => 'Pantalla LCD quebrada por golpe en mesón de trabajo',
                ],
            ],
            'Devuelto con desperfecto físico grave'
        );

        $this->assertFalse($devolucion->conforme);

        // 1. El activo pasa a 'no_disponible' (CP-21, no disponible para nuevos préstamos)
        $this->assertEquals('no_disponible', $this->equipoOsciloscopio->fresh()->estado);

        // 2. Se genera registro en tabla 'fallas' para el módulo de revisión técnica
        $falla = Falla::where('equipo_id', $this->equipoOsciloscopio->id)->first();
        $this->assertNotNull($falla);
        $this->assertEquals('reportada', $falla->estado);
        $this->assertEquals($this->panolero->id, $falla->reportada_por);
        $this->assertStringContainsString('Pantalla LCD quebrada', $falla->descripcion);

        // 3. El detalle queda marcado como 'con_danos' y la solicitud se cierra al no quedar unidades pendientes
        $this->assertEquals('con_danos', $detalle->fresh()->estado_devolucion);
        $this->assertEquals('cerrada', $solicitud->fresh()->estado);
    }

    /**
     * CP-21: Panel Volt permite registrar devolución e incidencia técnica.
     */
    public function test_panel_despacho_volt_permite_procesar_devolucion_con_dano(): void
    {
        $this->actingAs($this->panolero);

        $solicitud = $this->prestamoService->crearSolicitud(
            $this->docente,
            ['fecha_requerida' => Carbon::tomorrow()->toDateString()],
            [['equipo_id' => $this->equipoOsciloscopio->id, 'cantidad' => 1.0]]
        );

        $entrega = $this->prestamoService->entregarMateriales(
            $solicitud,
            $this->panolero,
            'Profesor Hernán',
            'docente'
        );

        $detalle = $solicitud->detalles->first();

        Volt::test('pages.despacho.index')
            ->call('abrirModalDevolucion', $entrega->id)
            ->set("itemsDevolucionForm.{$detalle->id}.conforme", false)
            ->set("itemsDevolucionForm.{$detalle->id}.descripcion_dano", 'Conector BNC suelto')
            ->call('confirmarDevolucion')
            ->assertHasNoErrors()
            ->assertSee('¡Devolución procesada exitosamente!');

        $this->assertEquals('no_disponible', $this->equipoOsciloscopio->fresh()->estado);
        $this->assertDatabaseHas('fallas', [
            'equipo_id' => $this->equipoOsciloscopio->id,
            'estado' => 'reportada',
        ]);
    }
}
