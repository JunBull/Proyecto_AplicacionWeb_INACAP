<?php

namespace Tests\Feature\HitoCuatro;

use App\Exceptions\OperacionDuplicadaException;
use App\Models\Carrera;
use App\Models\DetalleSolicitud;
use App\Models\Entrega;
use App\Models\Insumo;
use App\Models\MovimientoInventario;
use App\Models\SolicitudMaterial;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\PrestamoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CasoPruebaCP22Test extends TestCase
{
    use RefreshDatabase;

    protected Carrera $carrera;
    protected Ubicacion $ubicacion;
    protected User $docente;
    protected User $panolero;
    protected Insumo $insumoConsumible;
    protected Insumo $insumoRetornable;
    protected PrestamoService $prestamoService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prestamoService = app(PrestamoService::class);

        $this->carrera = Carrera::create([
            'codigo' => 'AGR-01',
            'nombre' => 'Ingeniería Agrícola',
            'activa' => true,
        ]);

        $this->ubicacion = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Invernadero y Pañol de Riego',
            'tipo' => 'laboratorio',
        ]);

        $this->docente = User::factory()->create([
            'name' => 'Profesor Samuel Silva',
            'email' => 'samuel.silva@inacap.cl',
            'rol' => 'docente',
        ]);

        $this->panolero = User::factory()->create([
            'name' => 'Encargado Luis Morales',
            'email' => 'luis.morales@inacap.cl',
            'rol' => 'encargado_panol',
        ]);

        $this->insumoConsumible = Insumo::create([
            'codigo_barras' => 'INS-CON-FAS-GOTE-100',
            'nombre' => 'Microaspersor Giratorio 360',
            'unidad' => 'unidad',
            'es_retornable' => false,
            'stock_actual' => 30.0,
            'stock_minimo' => 5.0,
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        $this->insumoRetornable = Insumo::create([
            'codigo_barras' => 'INS-RET-TEST-PH-01',
            'nombre' => 'Medidor Digital Portátil de pH y Humedad',
            'unidad' => 'unidad',
            'es_retornable' => true,
            'stock_actual' => 5.0,
            'stock_minimo' => 1.0,
            'ubicacion_id' => $this->ubicacion->id,
        ]);
    }

    /**
     * CP-22: Intento de doble entrega por reenvío o doble clic.
     * Detecta la duplicación a nivel de servidor, evitando duplicar movimientos de kardex o existencias.
     */
    public function test_reenvio_confirmacion_entrega_es_bloqueado_por_idempotencia(): void
    {
        $solicitud = $this->prestamoService->crearSolicitud(
            $this->docente,
            ['fecha_requerida' => Carbon::tomorrow()->toDateString()],
            [
                ['insumo_id' => $this->insumoConsumible->id, 'cantidad' => 10.0],
            ]
        );

        // 1. Primera confirmación legítima
        $entrega1 = $this->prestamoService->entregarMateriales(
            $solicitud,
            $this->panolero,
            'Profesor Samuel Silva',
            'docente'
        );

        $this->assertInstanceOf(Entrega::class, $entrega1);
        $this->assertEquals(20.0, (float) $this->insumoConsumible->fresh()->stock_actual);
        $this->assertEquals(1, Entrega::where('solicitud_id', $solicitud->id)->count());
        $this->assertEquals(1, MovimientoInventario::where('solicitud_id', $solicitud->id)->count());

        // 2. Segundo intento duplicado (simulación de doble clic rápido o replay de petición)
        $bloqueadoConExito = false;
        try {
            $this->prestamoService->entregarMateriales(
                $solicitud,
                $this->panolero,
                'Profesor Samuel Silva',
                'docente'
            );
        } catch (OperacionDuplicadaException $e) {
            $bloqueadoConExito = true;
            $this->assertStringContainsString('operación duplicada', strtolower($e->getMessage()));
        }

        $this->assertTrue($bloqueadoConExito, 'El reenvío de la entrega debió ser rechazado por idempotencia.');

        // 3. Verificación de consistencia estricta en base de datos: existencias no sufren doble descuento
        $this->assertEquals(20.0, (float) $this->insumoConsumible->fresh()->stock_actual);
        $this->assertEquals(1, Entrega::where('solicitud_id', $solicitud->id)->count());
        $this->assertEquals(1, MovimientoInventario::where('solicitud_id', $solicitud->id)->count());
    }

    /**
     * CP-22: Intento de doble confirmación de devolución sobre un préstamo ya cerrado.
     */
    public function test_reenvio_confirmacion_devolucion_es_bloqueado_por_idempotencia(): void
    {
        $solicitud = $this->prestamoService->crearSolicitud(
            $this->docente,
            ['fecha_requerida' => Carbon::tomorrow()->toDateString()],
            [
                ['insumo_id' => $this->insumoRetornable->id, 'cantidad' => 2.0],
            ]
        );

        $entrega = $this->prestamoService->entregarMateriales(
            $solicitud,
            $this->panolero,
            'Alumno Práctica',
            'estudiante'
        );

        $detalle = $solicitud->detalles->first();

        // 1. Primera devolución completa legítima
        $this->prestamoService->registrarDevolucion(
            $entrega,
            $this->panolero,
            [
                ['detalle_id' => $detalle->id, 'cantidad' => 2.0, 'conforme' => true],
            ],
            'Devuelto a tiempo'
        );

        $this->assertEquals(5.0, (float) $this->insumoRetornable->fresh()->stock_actual);
        $this->assertEquals('cerrada', $solicitud->fresh()->estado);

        // 2. Reintento duplicado de devolución
        $this->expectException(OperacionDuplicadaException::class);
        $this->prestamoService->registrarDevolucion(
            $entrega,
            $this->panolero,
            [
                ['detalle_id' => $detalle->id, 'cantidad' => 2.0, 'conforme' => true],
            ],
            'Reintento duplicado de devolución'
        );

        // Stock del insumo no se puede inflar dos veces
        $this->assertEquals(5.0, (float) $this->insumoRetornable->fresh()->stock_actual);
    }
}
