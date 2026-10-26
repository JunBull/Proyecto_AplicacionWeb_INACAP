<?php

namespace Tests\Feature\HitoCuatro;

use App\Exceptions\StockInsuficienteException;
use App\Models\Carrera;
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

class CasoIntegracionPI03Test extends TestCase
{
    use RefreshDatabase;

    protected Carrera $carrera;
    protected Ubicacion $ubicacion;
    protected User $docente1;
    protected User $docente2;
    protected User $docente3;
    protected User $panolero;
    protected Insumo $insumoConsumible;
    protected PrestamoService $prestamoService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prestamoService = app(PrestamoService::class);

        $this->carrera = Carrera::create([
            'codigo' => 'MIN-01',
            'nombre' => 'Ingeniería en Minas',
            'activa' => true,
        ]);

        $this->ubicacion = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Pañol de Seguridad y Perforación',
            'tipo' => 'panol',
        ]);

        $this->docente1 = User::factory()->create([
            'name' => 'Profesor Alejandro Muñoz',
            'email' => 'alejandro.munoz@inacap.cl',
            'rol' => 'docente',
        ]);

        $this->docente2 = User::factory()->create([
            'name' => 'Profesor Gabriel Torres',
            'email' => 'gabriel.torres@inacap.cl',
            'rol' => 'docente',
        ]);

        $this->docente3 = User::factory()->create([
            'name' => 'Profesora Daniela Soto',
            'email' => 'daniela.soto@inacap.cl',
            'rol' => 'docente',
        ]);

        $this->panolero = User::factory()->create([
            'name' => 'Encargado Carlos Morales',
            'email' => 'carlos.morales.panol@inacap.cl',
            'rol' => 'encargado_panol',
        ]);

        // Existencias iniciales limitadas a exactamente 10 unidades
        $this->insumoConsumible = Insumo::create([
            'codigo_barras' => 'INS-CON-FILTRO-MIN-01',
            'nombre' => 'Filtro de Partículas P100 para Respirador',
            'unidad' => 'par',
            'es_retornable' => false,
            'stock_actual' => 10.0,
            'stock_minimo' => 2.0,
            'ubicacion_id' => $this->ubicacion->id,
        ]);
    }

    /**
     * PI-03: Salidas simultáneas de un insumo consumible cuyas cantidades sumadas superen las existencias.
     * El motor con bloqueo pesimista lockForUpdate() permite el despacho hasta agotar existencias y bloquea
     * atómicamente cualquier egreso que derive en saldo negativo.
     */
    public function test_salidas_concurrentes_no_pueden_generar_stock_negativo(): void
    {
        // Solicitud 1: 7 pares
        $solicitud1 = $this->prestamoService->crearSolicitud(
            $this->docente1,
            ['fecha_requerida' => Carbon::tomorrow()->toDateString()],
            [['insumo_id' => $this->insumoConsumible->id, 'cantidad' => 7.0]]
        );

        // Solicitud 2: 5 pares (7 + 5 = 12 > 10 iniciales)
        $solicitud2 = $this->prestamoService->crearSolicitud(
            $this->docente2,
            ['fecha_requerida' => Carbon::tomorrow()->toDateString()],
            [['insumo_id' => $this->insumoConsumible->id, 'cantidad' => 5.0]]
        );

        // 1. Despacho Solicitud 1: se entregan 7 pares exitosamente
        $entrega1 = $this->prestamoService->entregarMateriales(
            $solicitud1,
            $this->panolero,
            'Alejandro Muñoz',
            'docente'
        );

        $this->assertInstanceOf(Entrega::class, $entrega1);
        $stockIntermedio = (float) $this->insumoConsumible->fresh()->stock_actual;
        $this->assertEquals(3.0, $stockIntermedio);

        // 2. Despacho Solicitud 2: intenta entregar 5 pares pero solo quedan 3 disponibles
        $rechazoPorStockNegativo = false;
        try {
            $this->prestamoService->entregarMateriales(
                $solicitud2,
                $this->panolero,
                'Gabriel Torres',
                'docente'
            );
        } catch (StockInsuficienteException $e) {
            $rechazoPorStockNegativo = true;
            $this->assertStringContainsString('Stock insuficiente', $e->getMessage());
            $this->assertStringContainsString('Existencias físicas: 3', $e->getMessage());
        }

        $this->assertTrue(
            $rechazoPorStockNegativo,
            'El despacho concurrente debió ser bloqueado para impedir stock negativo de inventario.'
        );

        // 3. El stock debe permanecer estrictamente en 3.0 unidades
        $this->assertEquals(3.0, (float) $this->insumoConsumible->fresh()->stock_actual);
        $this->assertGreaterThanOrEqual(0.0, (float) $this->insumoConsumible->fresh()->stock_actual);

        // 4. Despacho Solicitud 3: se solicitan exactamente los 3 pares remanentes
        $solicitud3 = $this->prestamoService->crearSolicitud(
            $this->docente3,
            ['fecha_requerida' => Carbon::tomorrow()->toDateString()],
            [['insumo_id' => $this->insumoConsumible->id, 'cantidad' => 3.0]]
        );

        $entrega3 = $this->prestamoService->entregarMateriales(
            $solicitud3,
            $this->panolero,
            'Daniela Soto',
            'docente'
        );

        $this->assertInstanceOf(Entrega::class, $entrega3);

        // El stock final queda en 0.0 exacto sin inconsistencias
        $stockFinal = (float) $this->insumoConsumible->fresh()->stock_actual;
        $this->assertEquals(0.0, $stockFinal);

        // 5. Cualquier intento posterior con stock = 0 es rechazado de inmediato
        $solicitudExceso = $this->prestamoService->crearSolicitud(
            $this->docente1,
            ['fecha_requerida' => Carbon::tomorrow()->toDateString()],
            [['insumo_id' => $this->insumoConsumible->id, 'cantidad' => 1.0]]
        );

        $this->expectException(StockInsuficienteException::class);
        $this->prestamoService->entregarMateriales(
            $solicitudExceso,
            $this->panolero,
            'Docente Intento Fallido',
            'docente'
        );
    }
}
