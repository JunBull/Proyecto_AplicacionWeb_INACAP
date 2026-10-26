<?php

namespace Tests\Feature\HitoTres;

use App\Exceptions\SaldoInsuficienteException;
use App\Models\Carrera;
use App\Models\Compra;
use App\Models\Insumo;
use App\Models\Presupuesto;
use App\Models\Proveedor;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\CompraService;
use App\Services\PresupuestoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CasoPruebaCP15Test extends TestCase
{
    use RefreshDatabase;

    protected CompraService $compraService;

    protected PresupuestoService $presupuestoService;

    protected Carrera $carreraA;

    protected Carrera $carreraB;

    protected Presupuesto $presupuestoA;

    protected Presupuesto $presupuestoB;

    protected Proveedor $proveedor;

    protected User $panolero;

    protected Insumo $insumoA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->compraService = app(CompraService::class);
        $this->presupuestoService = app(PresupuestoService::class);

        // Carrera A: Electricidad
        $this->carreraA = Carrera::create([
            'codigo' => 'ELC-15',
            'nombre' => 'Electricidad Industrial',
            'activa' => true,
        ]);
        $this->presupuestoA = $this->presupuestoService->obtenerOCrearPresupuesto($this->carreraA, 2026, 3000000);

        // Carrera B: Informática
        $this->carreraB = Carrera::create([
            'codigo' => 'INF-15',
            'nombre' => 'Ingeniería en Informática',
            'activa' => true,
        ]);
        $this->presupuestoB = $this->presupuestoService->obtenerOCrearPresupuesto($this->carreraB, 2026, 5000000);

        $ubicacionA = Ubicacion::create([
            'carrera_id' => $this->carreraA->id,
            'nombre' => 'Pañol de Electricidad',
            'tipo' => 'panol',
        ]);

        $this->insumoA = Insumo::create([
            'codigo_barras' => 'INS-ELC-150',
            'nombre' => 'Canaleta Ranurada 40x40',
            'unidad' => 'metro',
            'es_retornable' => false,
            'stock_actual' => 20,
            'stock_minimo' => 5,
            'ubicacion_id' => $ubicacionA->id,
        ]);

        $this->proveedor = Proveedor::create([
            'razon_social' => 'Electro Materiales Araucanía',
            'rut' => '76.888.222-1',
            'correo' => 'ventas@electromateriales.cl',
            'tipo' => 'insumos',
            'activo' => true,
        ]);

        $this->panolero = User::factory()->create([
            'email' => 'panolero.cp15@inacap.cl',
            'rol' => 'encargado_panol',
        ]);
    }

    public function test_debita_exactamente_el_importe_de_la_carrera_correspondiente_sin_alterar_las_demas(): void
    {
        $saldoInicialA = (float) $this->presupuestoA->fresh()->saldo_actual;
        $saldoInicialB = (float) $this->presupuestoB->fresh()->saldo_actual;

        $montoCompra = 450000.0; // 45 metros a $10.000

        $compra = $this->compraService->registrarCompraEIngreso(
            [
                'proveedor_id' => $this->proveedor->id,
                'carrera_id' => $this->carreraA->id,
                'numero_documento' => 'FAC-45000',
                'fecha' => '2026-10-26',
            ],
            [
                [
                    'insumo_id' => $this->insumoA->id,
                    'descripcion' => 'Canaleta Ranurada 40x40',
                    'cantidad' => 45,
                    'costo_unitario' => 10000,
                ],
            ],
            $this->panolero
        );

        $this->assertInstanceOf(Compra::class, $compra);

        // Verificación 1: Carrera A debitada exactamente por $450.000
        $saldoFinalA = (float) $this->presupuestoService->consultarSaldo($this->carreraA, 2026);
        $this->assertEquals($saldoInicialA - $montoCompra, $saldoFinalA);
        $this->assertEquals(2550000.0, $saldoFinalA);

        // Verificación 2: Carrera B se mantiene completamente inalterada
        $saldoFinalB = (float) $this->presupuestoService->consultarSaldo($this->carreraB, 2026);
        $this->assertEquals($saldoInicialB, $saldoFinalB);
        $this->assertEquals(5000000.0, $saldoFinalB);

        // Verificación 3: Registro en bitácora movimientos_presupuesto
        $this->assertDatabaseHas('movimientos_presupuesto', [
            'presupuesto_id' => $this->presupuestoA->id,
            'compra_id' => $compra->id,
            'usuario_id' => $this->panolero->id,
            'tipo' => 'cargo',
            'monto' => $montoCompra,
        ]);
    }

    public function test_rechaza_compra_si_el_monto_supera_el_saldo_disponible_de_la_carrera(): void
    {
        $this->expectException(SaldoInsuficienteException::class);

        // Saldo actual Carrera A es $3.000.000; se intenta comprar por $4.000.000
        $this->compraService->registrarCompraEIngreso(
            [
                'proveedor_id' => $this->proveedor->id,
                'carrera_id' => $this->carreraA->id,
                'numero_documento' => 'FAC-EXCESO',
                'fecha' => '2026-10-26',
            ],
            [
                [
                    'insumo_id' => $this->insumoA->id,
                    'descripcion' => 'Canaleta Ranurada 40x40',
                    'cantidad' => 400,
                    'costo_unitario' => 10000,
                ],
            ],
            $this->panolero
        );
    }
}
