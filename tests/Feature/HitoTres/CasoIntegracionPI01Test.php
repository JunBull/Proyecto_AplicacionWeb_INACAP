<?php

namespace Tests\Feature\HitoTres;

use App\Models\Carrera;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\Insumo;
use App\Models\MovimientoInventario;
use App\Models\MovimientoPresupuesto;
use App\Models\Presupuesto;
use App\Models\Proveedor;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\CompraService;
use App\Services\PresupuestoService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CasoIntegracionPI01Test extends TestCase
{
    use RefreshDatabase;

    protected CompraService $compraService;

    protected PresupuestoService $presupuestoService;

    protected Carrera $carrera;

    protected Ubicacion $ubicacion;

    protected Proveedor $proveedor;

    protected Insumo $insumo1;

    protected Insumo $insumo2;

    protected User $panolero;

    protected Presupuesto $presupuesto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->compraService = app(CompraService::class);
        $this->presupuestoService = app(PresupuestoService::class);

        $this->carrera = Carrera::create([
            'codigo' => 'IND-01',
            'nombre' => 'Mantenimiento Industrial',
            'activa' => true,
        ]);

        $this->ubicacion = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Pañol de Máquinas y Herramientas',
            'tipo' => 'panol',
        ]);

        $this->proveedor = Proveedor::create([
            'razon_social' => 'Rodamientos y Transmisiones Temuco SpA',
            'rut' => '76.123.999-5',
            'correo' => 'ventas@rodamientos.cl',
            'tipo' => 'insumos',
            'activo' => true,
        ]);

        $this->insumo1 = Insumo::create([
            'codigo_barras' => 'INS-ROD-6204',
            'nombre' => 'Rodamiento Rígido de Bolas 6204-2RS',
            'unidad' => 'unidad',
            'es_retornable' => false,
            'stock_actual' => 10,
            'stock_minimo' => 5,
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        $this->insumo2 = Insumo::create([
            'codigo_barras' => 'INS-COR-A45',
            'nombre' => 'Correa Trapezoidal Perfil A-45',
            'unidad' => 'unidad',
            'es_retornable' => false,
            'stock_actual' => 8,
            'stock_minimo' => 4,
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        $this->panolero = User::factory()->create([
            'email' => 'panolero.pi01@inacap.cl',
            'rol' => 'encargado_panol',
        ]);

        // Presupuesto asignado 2026: $1.000.000
        $this->presupuesto = $this->presupuestoService->obtenerOCrearPresupuesto($this->carrera, 2026, 1000000);
    }

    public function test_confirmacion_atomica_exitosa_de_compra_multilinea_con_stock_y_presupuesto(): void
    {
        $detalles = [
            [
                'insumo_id' => $this->insumo1->id,
                'descripcion' => 'Rodamiento Rígido de Bolas 6204-2RS',
                'cantidad' => 20,
                'costo_unitario' => 5000,
            ],
            [
                'insumo_id' => $this->insumo2->id,
                'descripcion' => 'Correa Trapezoidal Perfil A-45',
                'cantidad' => 10,
                'costo_unitario' => 8000,
            ],
        ];

        // Monto total: (20 * 5000) + (10 * 8000) = 100.000 + 80.000 = 180.000
        $compra = $this->compraService->registrarCompraEIngreso(
            [
                'proveedor_id' => $this->proveedor->id,
                'carrera_id' => $this->carrera->id,
                'numero_documento' => 'FAC-PI01-01',
                'fecha' => '2026-10-26',
            ],
            $detalles,
            $this->panolero
        );

        $this->assertInstanceOf(Compra::class, $compra);
        $this->assertEquals(180000, (float) $compra->monto_total);

        // Insumos actualizados atómicamente
        $this->assertEquals(30, (float) $this->insumo1->fresh()->stock_actual); // 10 + 20
        $this->assertEquals(18, (float) $this->insumo2->fresh()->stock_actual); // 8 + 10

        // Presupuesto debitado atómicamente: 1.000.000 - 180.000 = 820.000
        $this->assertEquals(820000, (float) $this->presupuestoService->consultarSaldo($this->carrera, 2026));

        // Verificación de registros de auditoría
        $this->assertEquals(2, CompraDetalle::where('compra_id', $compra->id)->count());
        $this->assertEquals(2, MovimientoInventario::where('compra_id', $compra->id)->count());
        $this->assertEquals(1, MovimientoPresupuesto::where('compra_id', $compra->id)->count());
    }

    public function test_rollback_integral_y_cero_inconsistencias_ante_fallo_intermedio(): void
    {
        $saldoInicial = (float) $this->presupuesto->fresh()->saldo_actual;
        $stock1Inicial = (float) $this->insumo1->fresh()->stock_actual;
        $stock2Inicial = (float) $this->insumo2->fresh()->stock_actual;

        // Simulamos una falla forzada en el segundo ítem (ej. id de insumo inexistente forzando ModelNotFoundException)
        $detallesConError = [
            [
                'insumo_id' => $this->insumo1->id,
                'descripcion' => 'Rodamiento 6204',
                'cantidad' => 5,
                'costo_unitario' => 5000,
            ],
            [
                'insumo_id' => 999999, // Insumo no existente forzando ModelNotFoundException dentro de la transacción
                'descripcion' => 'Ítem con error de clave foránea',
                'cantidad' => 10,
                'costo_unitario' => 8000,
            ],
        ];

        try {
            $this->compraService->registrarCompraEIngreso(
                [
                    'proveedor_id' => $this->proveedor->id,
                    'carrera_id' => $this->carrera->id,
                    'numero_documento' => 'FAC-FALLIDA',
                    'fecha' => '2026-10-26',
                ],
                $detallesConError,
                $this->panolero
            );
            $this->fail('Se esperaba una excepción provocando el Rollback.');
        } catch (Exception $e) {
            // Se captura la falla intencionada
            $this->assertTrue(true);
        }

        // COMPROBACIÓN CRÍTICA PI-01: Ningún cambio parcial quedó persistido
        // 1. Saldo presupuestario intacto
        $this->assertEquals($saldoInicial, (float) $this->presupuesto->fresh()->saldo_actual);

        // 2. Stock de los insumos intacto
        $this->assertEquals($stock1Inicial, (float) $this->insumo1->fresh()->stock_actual);
        $this->assertEquals($stock2Inicial, (float) $this->insumo2->fresh()->stock_actual);

        // 3. No se creó la cabecera de Compra ni líneas ni movimientos
        $this->assertDatabaseMissing('compras', ['numero_documento' => 'FAC-FALLIDA']);
        $this->assertEquals(0, MovimientoPresupuesto::count());
        $this->assertEquals(0, MovimientoInventario::count());
        $this->assertEquals(0, CompraDetalle::count());
    }
}
