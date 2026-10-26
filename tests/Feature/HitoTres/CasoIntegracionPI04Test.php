<?php

namespace Tests\Feature\HitoTres;

use App\Exceptions\SaldoInsuficienteException;
use App\Models\Carrera;
use App\Models\Insumo;
use App\Models\Presupuesto;
use App\Models\Proveedor;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\CompraService;
use App\Services\PresupuestoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CasoIntegracionPI04Test extends TestCase
{
    use RefreshDatabase;

    protected CompraService $compraService;

    protected PresupuestoService $presupuestoService;

    protected Carrera $carrera;

    protected Ubicacion $ubicacion;

    protected Proveedor $proveedor;

    protected Insumo $insumo;

    protected User $panolero;

    protected Presupuesto $presupuesto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->compraService = app(CompraService::class);
        $this->presupuestoService = app(PresupuestoService::class);

        $this->carrera = Carrera::create([
            'codigo' => 'CON-01',
            'nombre' => 'Construcción Civil',
            'activa' => true,
        ]);

        $this->ubicacion = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Laboratorio de Resistencia de Materiales',
            'tipo' => 'laboratorio',
        ]);

        $this->proveedor = Proveedor::create([
            'razon_social' => 'Materiales de Construcción Ñielol Ltda.',
            'rut' => '76.321.654-9',
            'correo' => 'ventas@materialesnielol.cl',
            'tipo' => 'insumos',
            'activo' => true,
        ]);

        $this->insumo = Insumo::create([
            'codigo_barras' => 'INS-CON-CEM',
            'nombre' => 'Saco Cemento Especial 25kg',
            'unidad' => 'unidad',
            'es_retornable' => false,
            'stock_actual' => 50,
            'stock_minimo' => 10,
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        $this->panolero = User::factory()->create([
            'email' => 'panolero.pi04@inacap.cl',
            'rol' => 'encargado_panol',
        ]);

        // Fondo limitado de exactamente $100.000 para el año 2026
        $this->presupuesto = $this->presupuestoService->obtenerOCrearPresupuesto($this->carrera, 2026, 100000);
    }

    public function test_compras_secuenciales_y_concurrencia_bloquean_transaccion_que_exceda_saldo_remanente(): void
    {
        // Compra 1: $60.000 (10 sacos x $6.000). Debe procesarse exitosamente.
        $compra1 = $this->compraService->registrarCompraEIngreso(
            [
                'proveedor_id' => $this->proveedor->id,
                'carrera_id' => $this->carrera->id,
                'numero_documento' => 'FAC-C1',
                'fecha' => '2026-10-26',
            ],
            [
                [
                    'insumo_id' => $this->insumo->id,
                    'descripcion' => 'Saco Cemento 25kg',
                    'cantidad' => 10,
                    'costo_unitario' => 6000,
                ],
            ],
            $this->panolero
        );

        $this->assertNotNull($compra1);
        $this->assertEquals(40000, (float) $this->presupuestoService->consultarSaldo($this->carrera, 2026));

        // Compra 2 concurrente / simultánea: Solicita $50.000 (Supera el saldo disponible de $40.000)
        // Debe ser rechazada atómicamente por el servidor informando falta de fondos (PI-04)
        $rechazadaConExito = false;
        try {
            $this->compraService->registrarCompraEIngreso(
                [
                    'proveedor_id' => $this->proveedor->id,
                    'carrera_id' => $this->carrera->id,
                    'numero_documento' => 'FAC-C2-EXCESO',
                    'fecha' => '2026-10-26',
                ],
                [
                    [
                        'insumo_id' => $this->insumo->id,
                        'descripcion' => 'Saco Cemento 25kg',
                        'cantidad' => 10,
                        'costo_unitario' => 5000, // 10 * 5000 = $50.000 > $40.000
                    ],
                ],
                $this->panolero
            );
        } catch (SaldoInsuficienteException $e) {
            $rechazadaConExito = true;
            $this->assertStringContainsString('Saldo presupuestario insuficiente', $e->getMessage());
        }

        $this->assertTrue($rechazadaConExito, 'La compra que excedía el saldo remanente debió ser rechazada.');

        // El saldo remanente debe mantenerse estrictamente intacto en $40.000
        $this->assertEquals(40000, (float) $this->presupuestoService->consultarSaldo($this->carrera, 2026));

        // Compra 3: Compra por exactamente los $40.000 remanentes (8 sacos x $5.000)
        // Debe copar el límite sin caer en saldo negativo
        $compra3 = $this->compraService->registrarCompraEIngreso(
            [
                'proveedor_id' => $this->proveedor->id,
                'carrera_id' => $this->carrera->id,
                'numero_documento' => 'FAC-C3-EXACTA',
                'fecha' => '2026-10-26',
            ],
            [
                [
                    'insumo_id' => $this->insumo->id,
                    'descripcion' => 'Saco Cemento 25kg',
                    'cantidad' => 8,
                    'costo_unitario' => 5000, // 8 * 5000 = $40.000
                ],
            ],
            $this->panolero
        );

        $this->assertNotNull($compra3);

        // Saldo final es exactamente $0 (sin inconsistencias ni stock negativo)
        $saldoFinal = (float) $this->presupuestoService->consultarSaldo($this->carrera, 2026);
        $this->assertEquals(0.0, $saldoFinal);
        $this->assertGreaterThanOrEqual(0.0, $saldoFinal);

        // Cualquier intento posterior debe ser bloqueado de inmediato
        $this->expectException(SaldoInsuficienteException::class);
        $this->compraService->registrarCompraEIngreso(
            [
                'proveedor_id' => $this->proveedor->id,
                'carrera_id' => $this->carrera->id,
                'numero_documento' => 'FAC-C4-SIN-FONDOS',
                'fecha' => '2026-10-26',
            ],
            [
                [
                    'insumo_id' => $this->insumo->id,
                    'descripcion' => 'Saco Cemento 25kg',
                    'cantidad' => 1,
                    'costo_unitario' => 5000,
                ],
            ],
            $this->panolero
        );
    }
}
