<?php

namespace Tests\Feature\HitoSiete;

use App\Exceptions\StockReservadoClasesException;
use App\Models\Carrera;
use App\Models\DetalleSolicitud;
use App\Models\Equipo;
use App\Models\Insumo;
use App\Models\ParametroSistema;
use App\Models\SolicitudMaterial;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\PrestamoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CasoPruebaCP25Test extends TestCase
{
    use RefreshDatabase;

    protected User $panolero;

    protected User $docente;

    protected Carrera $carrera;

    protected Ubicacion $bodega;

    protected Ubicacion $taller;

    protected PrestamoService $prestamoService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prestamoService = app(PrestamoService::class);

        $this->panolero = User::factory()->create([
            'name' => 'Pañolero Central',
            'email' => 'panolero.central@inacap.cl',
            'rol' => 'encargado_panol',
            'activo' => true,
        ]);

        $this->docente = User::factory()->create([
            'name' => 'Docente Préstamo',
            'email' => 'docente.prestamo@inacap.cl',
            'rol' => 'docente',
            'activo' => true,
        ]);

        $this->carrera = Carrera::create([
            'codigo' => 'TEL-02',
            'nombre' => 'Telecomunicaciones y Redes',
            'activa' => true,
        ]);

        $this->bodega = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Pañol Central Telecomunicaciones',
            'tipo' => 'panol',
        ]);

        $this->taller = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Laboratorio Cisco Lab-1',
            'tipo' => 'laboratorio',
        ]);

        ParametroSistema::set('stock_minimo_clases_default', 5, 'integer', 'Reserva mínima para clases');
    }

    /**
     * CP-25: Bloquea préstamo a estudiante cuando un insumo retornable no supera el stock mínimo reservado para clases.
     */
    public function test_bloquea_prestamo_a_estudiante_cuando_insumo_retornable_no_supera_reserva_docente(): void
    {
        // Insumo retornable con 5 unidades en stock, pero con 5 unidades reservadas para clases
        $insumo = Insumo::create([
            'codigo_barras' => 'INS-TEST-MULT-01',
            'nombre' => 'Multímetro Digital Fluke 115',
            'unidad' => 'unidad',
            'es_retornable' => true,
            'stock_actual' => 5.0,
            'stock_minimo' => 2.0,
            'stock_minimo_clases' => 5.0, // Las 5 unidades están comprometidas para docencia
            'ubicacion_id' => $this->bodega->id,
        ]);

        $solicitud = SolicitudMaterial::create([
            'solicitante_id' => $this->panolero->id,
            'estado' => 'aprobada',
            'fecha_solicitud' => now(),
            'fecha_requerida' => now()->toDateString(),
            'ubicacion_destino_id' => $this->taller->id,
        ]);

        $detalle = DetalleSolicitud::create([
            'solicitud_id' => $solicitud->id,
            'insumo_id' => $insumo->id,
            'cantidad_solicitada' => 1.0,
            'cantidad_entregada' => 0.0,
            'cantidad_devuelta' => 0.0,
            'estado_devolucion' => 'pendiente',
        ]);

        $this->expectException(StockReservadoClasesException::class);
        $this->expectExceptionMessage('Stock reservado para clases');

        $this->prestamoService->registrarPrestamoEstudiante(
            solicitud: $solicitud,
            entregador: $this->panolero,
            estudianteNombre: 'Estudiante Juan Pérez',
            credencialRut: '20.123.456-7',
            credencialVerificada: true,
            horaEntrega: '11:00:00',
            horaDevolucionAcordada: '13:00:00',
            detallesAEntregar: [$detalle->id]
        );
    }

    /**
     * CP-25: Bloquea préstamo a estudiante cuando el equipo tiene la bandera reservado_clases activa.
     */
    public function test_bloquea_prestamo_a_estudiante_cuando_equipo_tiene_bandera_reservado_clases(): void
    {
        $equipo = Equipo::create([
            'codigo_barras' => 'EQ-RESERV-001',
            'nombre' => 'Generador de Ondas Arbitrarias',
            'marca' => 'Rigol',
            'modelo' => 'DG1022Z',
            'numero_serie' => 'SN-RIGOL-001',
            'estado' => 'disponible',
            'ubicacion_id' => $this->bodega->id,
            'reservado_clases' => true,
        ]);

        $solicitud = SolicitudMaterial::create([
            'solicitante_id' => $this->panolero->id,
            'estado' => 'aprobada',
            'fecha_solicitud' => now(),
            'fecha_requerida' => now()->toDateString(),
            'ubicacion_destino_id' => $this->taller->id,
        ]);

        $detalle = DetalleSolicitud::create([
            'solicitud_id' => $solicitud->id,
            'equipo_id' => $equipo->id,
            'cantidad_solicitada' => 1.0,
            'cantidad_entregada' => 0.0,
            'cantidad_devuelta' => 0.0,
            'estado_devolucion' => 'pendiente',
        ]);

        $this->expectException(StockReservadoClasesException::class);
        $this->expectExceptionMessage('reservado exclusivamente para clases docentes');

        $this->prestamoService->registrarPrestamoEstudiante(
            solicitud: $solicitud,
            entregador: $this->panolero,
            estudianteNombre: 'Estudiante María Soto',
            credencialRut: '21.987.654-3',
            credencialVerificada: true,
            horaEntrega: '14:00:00',
            horaDevolucionAcordada: '16:00:00',
            detallesAEntregar: [$detalle->id]
        );
    }

    /**
     * CP-25: En la interfaz Livewire de Despacho, se captura la excepción y se informa al usuario.
     */
    public function test_interfaz_despacho_despliega_alerta_de_reserva_docente_al_rechazar_prestamo(): void
    {
        $this->actingAs($this->panolero);

        $equipo = Equipo::create([
            'codigo_barras' => 'EQ-RESERV-002',
            'nombre' => 'Analizador de Espectro RF',
            'marca' => 'Rohde & Schwarz',
            'modelo' => 'FPH',
            'numero_serie' => 'SN-RS-002',
            'estado' => 'disponible',
            'ubicacion_id' => $this->bodega->id,
            'reservado_clases' => true,
        ]);

        $solicitud = SolicitudMaterial::create([
            'solicitante_id' => $this->panolero->id,
            'estado' => 'aprobada',
            'fecha_solicitud' => now(),
            'fecha_requerida' => now()->toDateString(),
            'ubicacion_destino_id' => $this->taller->id,
        ]);

        $detalle = DetalleSolicitud::create([
            'solicitud_id' => $solicitud->id,
            'equipo_id' => $equipo->id,
            'cantidad_solicitada' => 1.0,
            'cantidad_entregada' => 0.0,
            'cantidad_devuelta' => 0.0,
            'estado_devolucion' => 'pendiente',
        ]);

        Volt::test('pages.despacho.index')
            ->call('abrirModalEntrega', $solicitud->id)
            ->set('receptorTipo', 'estudiante')
            ->set('receptorNombre', 'Estudiante Carlos Valenzuela')
            ->set('receptorCredencialRut', '19.876.543-2')
            ->set('credencialVerificada', true)
            ->set('horaDevolucionAcordada', '18:00')
            ->set('detallesSeleccionadosIds', [$detalle->id])
            ->call('confirmarEntrega')
            ->assertSee('reservado exclusivamente para clases docentes');

        // Verificar que no se creó ninguna entrega en la base de datos
        $this->assertDatabaseMissing('entregas', [
            'solicitud_id' => $solicitud->id,
        ]);

        // Verificar que el equipo permanece en estado disponible
        $this->assertEquals('disponible', $equipo->fresh()->estado);
    }
}
