<?php

namespace Tests\Feature\HitoDos;

use App\Models\Carrera;
use App\Models\Insumo;
use App\Models\MovimientoInventario;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\InventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CasoPruebaCP13Test extends TestCase
{
    use RefreshDatabase;

    protected Carrera $carrera;
    protected Ubicacion $ubicacion;
    protected User $panolero;
    protected InventarioService $inventarioService;
    protected Insumo $insumo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->carrera = Carrera::create([
            'codigo' => 'INF-08',
            'nombre' => 'Ingeniería en Informática',
            'activa' => true,
        ]);

        $this->ubicacion = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Bodega de Materiales',
            'tipo' => 'panol',
            'padre_id' => null,
        ]);

        $this->panolero = User::factory()->create([
            'email' => 'panolero.cp13@inacap.cl',
            'rol' => 'encargado_panol',
        ]);

        $this->inventarioService = app(InventarioService::class);

        $this->insumo = $this->inventarioService->registrarInsumo([
            'codigo_barras' => 'INS-TEST-CP13',
            'nombre' => 'Patch Cord Fibra Óptica LC-LC Monomodo 3m',
            'unidad' => 'unidad',
            'es_retornable' => false,
            'stock_actual' => 10,
            'stock_minimo' => 4,
            'ubicacion_id' => $this->ubicacion->id,
        ]);
    }

    /**
     * CP-13: Ingreso con cantidad válida incrementa stock y crea registro en el kardex inmutable.
     */
    public function test_ingreso_valido_incrementa_stock_y_registra_kardex(): void
    {
        $movimiento = $this->inventarioService->registrarIngreso(
            $this->insumo,
            15,
            $this->panolero,
            'ingreso_manual'
        );

        $this->assertInstanceOf(MovimientoInventario::class, $movimiento);
        $this->assertEquals(15, (float) $movimiento->cantidad);
        $this->assertEquals('ingreso_manual', $movimiento->tipo);

        $this->insumo->refresh();
        $this->assertEquals(25, (float) $this->insumo->stock_actual);

        $this->assertDatabaseHas('movimientos_inventario', [
            'id' => $movimiento->id,
            'insumo_id' => $this->insumo->id,
            'usuario_id' => $this->panolero->id,
            'cantidad' => 15,
            'tipo' => 'ingreso_manual',
        ]);
    }

    /**
     * CP-13: Salida con cantidad válida decrementa stock y crea registro en kardex.
     */
    public function test_salida_valida_decrementa_stock_con_bloqueo_pesimista(): void
    {
        $movimiento = $this->inventarioService->registrarSalida(
            $this->insumo,
            4,
            $this->panolero,
            'salida_consumo'
        );

        $this->assertEquals(4, (float) $movimiento->cantidad);
        $this->assertEquals('salida_consumo', $movimiento->tipo);

        $this->insumo->refresh();
        $this->assertEquals(6, (float) $this->insumo->stock_actual);

        $this->assertDatabaseHas('movimientos_inventario', [
            'insumo_id' => $this->insumo->id,
            'cantidad' => 4,
            'tipo' => 'salida_consumo',
        ]);
    }

    /**
     * CP-13: El servidor bloquea terminantemente cantidades cero o negativas en movimientos de inventario.
     */
    public function test_bloqueo_cantidades_cero_y_negativas_en_ingresos_y_salidas(): void
    {
        $cantidadesInvalidas = [0, -1, -10.5, -0.01];

        foreach ($cantidadesInvalidas as $cant) {
            // 1. Bloqueo en ingresos
            try {
                $this->inventarioService->registrarIngreso($this->insumo, $cant, $this->panolero);
                $this->fail("El servidor debió bloquear un ingreso con cantidad inválida: {$cant}");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('mayor a cero', $e->getMessage());
            }

            // 2. Bloqueo en salidas
            try {
                $this->inventarioService->registrarSalida($this->insumo, $cant, $this->panolero);
                $this->fail("El servidor debió bloquear una salida con cantidad inválida: {$cant}");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('mayor a cero', $e->getMessage());
            }
        }

        // El stock debe permanecer inalterado en 10 y sin movimientos en kardex
        $this->insumo->refresh();
        $this->assertEquals(10, (float) $this->insumo->stock_actual);
        $this->assertEquals(0, MovimientoInventario::count());
    }

    /**
     * CP-13: El servidor bloquea salidas con cantidades superiores a las existencias actuales.
     */
    public function test_bloqueo_salidas_superiores_al_stock_actual(): void
    {
        $this->assertEquals(10, (float) $this->insumo->stock_actual);

        try {
            $this->inventarioService->registrarSalida($this->insumo, 11, $this->panolero);
            $this->fail('El servidor debió bloquear la salida de 11 unidades habiendo solo 10 en stock.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Stock insuficiente', $e->getMessage());
        }

        // Verificar que el stock no bajó ni pasó a negativo
        $this->insumo->refresh();
        $this->assertEquals(10, (float) $this->insumo->stock_actual);
        $this->assertEquals(0, MovimientoInventario::count());
    }
}
