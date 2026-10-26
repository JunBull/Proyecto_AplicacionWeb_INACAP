<?php

namespace Tests\Feature\HitoDos;

use App\Models\Carrera;
use App\Models\Insumo;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\InventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CasoPruebaCP14Test extends TestCase
{
    use RefreshDatabase;

    protected Carrera $carrera;
    protected Ubicacion $ubicacion;
    protected User $panolero;
    protected InventarioService $inventarioService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->carrera = Carrera::create([
            'codigo' => 'INF-09',
            'nombre' => 'Ingeniería en Informática',
            'activa' => true,
        ]);

        $this->ubicacion = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Almacén de Consumibles',
            'tipo' => 'panol',
            'padre_id' => null,
        ]);

        $this->panolero = User::factory()->create([
            'email' => 'panolero.cp14@inacap.cl',
            'rol' => 'encargado_panol',
        ]);

        $this->inventarioService = app(InventarioService::class);
    }

    /**
     * CP-14 / KPI-04: Se gatilla la condición de alerta de stock crítico cuando stock_actual <= stock_minimo.
     */
    public function test_alerta_stock_critico_al_llegar_o_bajar_del_stock_minimo_kpi_04(): void
    {
        // Insumo con stock_actual = 8 y stock_minimo = 5
        $insumo = $this->inventarioService->registrarInsumo([
            'codigo_barras' => 'INS-CRIT-001',
            'nombre' => 'Rollo de Estaño para Soldar 60/40 0.8mm',
            'unidad' => 'unidad',
            'es_retornable' => false,
            'stock_actual' => 8,
            'stock_minimo' => 5,
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        // Estado inicial: 8 > 5 -> NO crítico
        $this->assertFalse($this->inventarioService->esStockCritico($insumo));
        $this->assertFalse($this->inventarioService->obtenerInsumosCriticos()->contains($insumo));

        // 1. Salida de 3 unidades: stock_actual = 5 == stock_minimo -> GATILLA ALERTA CRÍTICA
        $this->inventarioService->registrarSalida($insumo, 3, $this->panolero);
        $insumo->refresh();

        $this->assertEquals(5, (float) $insumo->stock_actual);
        $this->assertTrue(
            $this->inventarioService->esStockCritico($insumo),
            'Al alcanzar exactamente el stock mínimo (stock_actual == stock_minimo) DEBE gatillarse la alerta.'
        );
        $this->assertTrue($this->inventarioService->obtenerInsumosCriticos()->contains($insumo));

        // 2. Salida adicional de 2 unidades: stock_actual = 3 < stock_minimo -> ALERTA PERMANECE ACTIVA
        $this->inventarioService->registrarSalida($insumo, 2, $this->panolero);
        $insumo->refresh();

        $this->assertEquals(3, (float) $insumo->stock_actual);
        $this->assertTrue(
            $this->inventarioService->esStockCritico($insumo),
            'Al quedar por debajo del stock mínimo (stock_actual < stock_minimo) DEBE mantenerse la alerta.'
        );
    }

    /**
     * CP-14: La alerta se desactiva automáticamente tras el ingreso de reposición de existencias.
     */
    public function test_desactivacion_alerta_tras_ingreso_de_reposicion(): void
    {
        // Insumo en estado crítico: stock_actual = 2, stock_minimo = 5
        $insumo = $this->inventarioService->registrarInsumo([
            'codigo_barras' => 'INS-REPO-002',
            'nombre' => 'Alcohol Isopropílico en Spray 400ml',
            'unidad' => 'frasco',
            'es_retornable' => false,
            'stock_actual' => 2,
            'stock_minimo' => 5,
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        $this->assertTrue($this->inventarioService->esStockCritico($insumo));
        $this->assertCount(1, $this->inventarioService->obtenerInsumosCriticos());

        // Ingreso de reposición: 10 frascos -> stock_actual pasa a 12 (> 5)
        $this->inventarioService->registrarIngreso($insumo, 10, $this->panolero, 'ingreso_manual');
        $insumo->refresh();

        $this->assertEquals(12, (float) $insumo->stock_actual);

        // La alerta DEBE desactivarse
        $this->assertFalse(
            $this->inventarioService->esStockCritico($insumo),
            'La alerta de stock crítico debe desactivarse una vez que las existencias superan el stock mínimo.'
        );

        $this->assertCount(0, $this->inventarioService->obtenerInsumosCriticos());
    }
}
