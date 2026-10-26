<?php

namespace Tests\Feature\HitoCuatro;

use App\Models\Carrera;
use App\Models\DetalleSolicitud;
use App\Models\Entrega;
use App\Models\Equipo;
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

class CasoPruebaCP20Test extends TestCase
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
            'codigo' => 'MEC-01',
            'nombre' => 'Ingeniería Mecánica en Mantenimiento Industrial',
            'activa' => true,
        ]);

        $this->ubicacion = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Taller de Soldadura y Mecanizado',
            'tipo' => 'panol',
        ]);

        $this->docente = User::factory()->create([
            'name' => 'Profesor Roberto Tapia',
            'email' => 'roberto.tapia@inacap.cl',
            'rol' => 'docente',
        ]);

        $this->panolero = User::factory()->create([
            'name' => 'Encargado Juan González',
            'email' => 'juan.gonzalez@inacap.cl',
            'rol' => 'encargado_panol',
        ]);

        $this->equipo = Equipo::create([
            'codigo_barras' => 'EQ-MEC-SOLD-001',
            'nombre' => 'Máquina Soldadora Inverter 200A',
            'marca' => 'Indura',
            'modelo' => 'Inverpower 200',
            'numero_serie' => 'IND-2026-991',
            'estado' => 'disponible',
            'criticidad' => 'alta',
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        $this->insumoConsumible = Insumo::create([
            'codigo_barras' => 'INS-CON-ELEC-6011',
            'nombre' => 'Electrodo E-6011 3/32 (Kg)',
            'unidad' => 'kg',
            'es_retornable' => false,
            'stock_actual' => 25.0,
            'stock_minimo' => 5.0,
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        $this->insumoRetornable = Insumo::create([
            'codigo_barras' => 'INS-RET-MASC-FOTOSEN',
            'nombre' => 'Máscara Fotosensible para Soldar',
            'unidad' => 'unidad',
            'es_retornable' => true,
            'stock_actual' => 8.0,
            'stock_minimo' => 2.0,
            'ubicacion_id' => $this->ubicacion->id,
        ]);
    }

    /**
     * CP-20: Entrega de materiales a un docente presencial en mesón de pañol.
     */
    public function test_entrega_materiales_a_docente_presencial(): void
    {
        $solicitud = $this->prestamoService->crearSolicitud(
            $this->docente,
            ['fecha_requerida' => Carbon::tomorrow()->toDateString()],
            [
                ['insumo_id' => $this->insumoConsumible->id, 'cantidad' => 5.0],
                ['equipo_id' => $this->equipo->id, 'cantidad' => 1.0],
            ]
        );

        $entrega = $this->prestamoService->entregarMateriales(
            $solicitud,
            $this->panolero,
            'Roberto Tapia',
            'docente'
        );

        $this->assertInstanceOf(Entrega::class, $entrega);
        $this->assertEquals($solicitud->id, $entrega->solicitud_id);
        $this->assertEquals($this->panolero->id, $entrega->entregado_por);
        $this->assertEquals('Roberto Tapia', $entrega->receptor_nombre);
        $this->assertEquals('docente', $entrega->receptor_tipo);

        // Verificación de stock y estados diferenciados
        $this->assertEquals(20.0, (float) $this->insumoConsumible->fresh()->stock_actual);
        $this->assertEquals('prestado', $this->equipo->fresh()->estado);

        // Solicitud contiene equipo retornable, por tanto queda abierta a la espera de devolución
        $this->assertEquals('entregada_abierta', $solicitud->fresh()->estado);
    }

    /**
     * CP-20: Entrega de materiales a un estudiante sin crear usuario en la tabla usuarios.
     */
    public function test_entrega_materiales_a_estudiante_sin_crear_usuario(): void
    {
        $solicitud = $this->prestamoService->crearSolicitud(
            $this->docente,
            ['fecha_requerida' => Carbon::tomorrow()->toDateString()],
            [
                ['insumo_id' => $this->insumoRetornable->id, 'cantidad' => 2.0],
            ]
        );

        $totalUsuariosAntes = User::count();

        $entrega = $this->prestamoService->entregarMateriales(
            $solicitud,
            $this->panolero,
            'Pedro Soto (Alumno Taller Soldadura)',
            'estudiante'
        );

        $this->assertEquals('estudiante', $entrega->receptor_tipo);
        $this->assertEquals('Pedro Soto (Alumno Taller Soldadura)', $entrega->receptor_nombre);

        // Regla innegociable: no se crean usuarios en la base de datos para alumnos
        $this->assertEquals($totalUsuariosAntes, User::count());
        $this->assertDatabaseMissing('usuarios', ['email' => 'pedro.soto@inacap.cl']);
    }

    /**
     * CP-20: Actualización diferenciada de salida definitiva (consumible) vs préstamo activo (retornable).
     */
    public function test_actualizacion_diferenciada_consumible_vs_retornable(): void
    {
        $solicitudConsumible = $this->prestamoService->crearSolicitud(
            $this->docente,
            ['fecha_requerida' => Carbon::tomorrow()->toDateString()],
            [
                ['insumo_id' => $this->insumoConsumible->id, 'cantidad' => 3.0],
            ]
        );

        // 1. Despacho exclusivo de consumibles
        $this->prestamoService->entregarMateriales(
            $solicitudConsumible,
            $this->panolero,
            'Estudiante Práctica',
            'estudiante'
        );

        // Kardex: debe registrar salida_consumo
        $movConsumo = MovimientoInventario::where('solicitud_id', $solicitudConsumible->id)->first();
        $this->assertNotNull($movConsumo);
        $this->assertEquals('salida_consumo', $movConsumo->tipo);
        $this->assertEquals(3.0, (float) $movConsumo->cantidad);

        // Estado devolución en detalle: no_aplica
        $detalleConsumible = DetalleSolicitud::where('solicitud_id', $solicitudConsumible->id)->first();
        $this->assertEquals('no_aplica', $detalleConsumible->estado_devolucion);

        // La solicitud al no tener nada retornable queda inmediatamente 'cerrada'
        $this->assertEquals('cerrada', $solicitudConsumible->fresh()->estado);

        // 2. Despacho de retornable
        $solicitudRetornable = $this->prestamoService->crearSolicitud(
            $this->docente,
            ['fecha_requerida' => Carbon::tomorrow()->toDateString()],
            [
                ['insumo_id' => $this->insumoRetornable->id, 'cantidad' => 1.0],
            ]
        );

        $this->prestamoService->entregarMateriales(
            $solicitudRetornable,
            $this->panolero,
            'Profesor Roberto',
            'docente'
        );

        // Kardex: debe registrar salida_prestamo
        $movPrestamo = MovimientoInventario::where('solicitud_id', $solicitudRetornable->id)->first();
        $this->assertNotNull($movPrestamo);
        $this->assertEquals('salida_prestamo', $movPrestamo->tipo);

        // Estado devolución: pendiente
        $detalleRetornable = DetalleSolicitud::where('solicitud_id', $solicitudRetornable->id)->first();
        $this->assertEquals('pendiente', $detalleRetornable->estado_devolucion);
        $this->assertEquals('entregada_abierta', $solicitudRetornable->fresh()->estado);
    }

    /**
     * CP-20: Prueba del panel Livewire de despacho por pañolero.
     */
    public function test_panel_despacho_livewire_permite_confirmar_entrega(): void
    {
        $this->actingAs($this->panolero);

        $solicitud = $this->prestamoService->crearSolicitud(
            $this->docente,
            ['fecha_requerida' => Carbon::tomorrow()->toDateString()],
            [['insumo_id' => $this->insumoConsumible->id, 'cantidad' => 2.0]]
        );

        $detalle = $solicitud->detalles->first();

        Volt::test('pages.despacho.index')
            ->call('abrirModalEntrega', $solicitud->id)
            ->set('receptorNombre', 'Docente en Mesón')
            ->set('receptorTipo', 'docente')
            ->set('detallesSeleccionadosIds', [$detalle->id])
            ->call('confirmarEntrega')
            ->assertHasNoErrors()
            ->assertSee('¡Despacho confirmado exitosamente!');

        $this->assertDatabaseHas('entregas', [
            'solicitud_id' => $solicitud->id,
            'receptor_nombre' => 'Docente en Mesón',
            'receptor_tipo' => 'docente',
        ]);
    }
}
