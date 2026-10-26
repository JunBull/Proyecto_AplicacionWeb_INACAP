<?php

namespace Tests\Feature\HitoDos;

use App\Models\Carrera;
use App\Models\Insumo;
use App\Models\Ubicacion;
use App\Services\InventarioService;
use App\Services\UbicacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CasoPruebaCP11Test extends TestCase
{
    use RefreshDatabase;

    protected Carrera $carrera;
    protected UbicacionService $ubicacionService;
    protected InventarioService $inventarioService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->carrera = Carrera::create([
            'codigo' => 'INF-06',
            'nombre' => 'Ingeniería en Informática',
            'activa' => true,
        ]);

        $this->ubicacionService = app(UbicacionService::class);
        $this->inventarioService = app(InventarioService::class);
    }

    /**
     * CP-11: Se registran con fidelidad los niveles jerárquicos multinivel:
     * Carrera -> Pañol -> Laboratorio -> Mueble -> Cajón / Puerta.
     */
    public function test_jerarquia_completa_ubicaciones_multinivel(): void
    {
        // 1. Nivel Pañol
        $panolCentral = $this->ubicacionService->registrarUbicacion([
            'carrera_id' => $this->carrera->id,
            'padre_id' => null,
            'tipo' => 'panol',
            'nombre' => 'Pañol Central de Informática',
        ]);

        // 2. Nivel Laboratorio
        $labRedes = $this->ubicacionService->registrarUbicacion([
            'carrera_id' => $this->carrera->id,
            'padre_id' => $panolCentral->id,
            'tipo' => 'laboratorio',
            'nombre' => 'Laboratorio 302 Redes',
        ]);

        // 3. Nivel Mueble
        $muebleHerramientas = $this->ubicacionService->registrarUbicacion([
            'carrera_id' => $this->carrera->id,
            'padre_id' => $labRedes->id,
            'tipo' => 'mueble',
            'nombre' => 'Mueble Metálico Herramientas A',
        ]);

        // 4. Nivel Cajón / Puerta
        $cajonConectores = $this->ubicacionService->registrarUbicacion([
            'carrera_id' => $this->carrera->id,
            'padre_id' => $muebleHerramientas->id,
            'tipo' => 'cajon',
            'nombre' => 'Cajón 03 Conectores RJ45',
        ]);

        $this->assertEquals($panolCentral->id, $labRedes->padre_id);
        $this->assertEquals($labRedes->id, $muebleHerramientas->padre_id);
        $this->assertEquals($muebleHerramientas->id, $cajonConectores->padre_id);

        $rutaCompleta = $this->ubicacionService->obtenerRutaCompleta($cajonConectores);

        $this->assertStringContainsString('Ingeniería en Informática', $rutaCompleta);
        $this->assertStringContainsString('Pañol Central de Informática (panol)', $rutaCompleta);
        $this->assertStringContainsString('Laboratorio 302 Redes (laboratorio)', $rutaCompleta);
        $this->assertStringContainsString('Mueble Metálico Herramientas A (mueble)', $rutaCompleta);
        $this->assertStringContainsString('Cajón 03 Conectores RJ45 (cajon)', $rutaCompleta);
    }

    /**
     * CP-11: Registrar y modificar la ubicación física detallada de un insumo para localización física inmediata.
     */
    public function test_asignar_y_modificar_ubicacion_detallada_insumo(): void
    {
        $panol = $this->ubicacionService->registrarUbicacion([
            'carrera_id' => $this->carrera->id,
            'padre_id' => null,
            'tipo' => 'panol',
            'nombre' => 'Pañol Sector B',
        ]);

        $mueble1 = $this->ubicacionService->registrarUbicacion([
            'carrera_id' => $this->carrera->id,
            'padre_id' => $panol->id,
            'tipo' => 'mueble',
            'nombre' => 'Estante Metálico 01',
        ]);

        $cajon1 = $this->ubicacionService->registrarUbicacion([
            'carrera_id' => $this->carrera->id,
            'padre_id' => $mueble1->id,
            'tipo' => 'cajon',
            'nombre' => 'Cajonera 1A',
        ]);

        $mueble2 = $this->ubicacionService->registrarUbicacion([
            'carrera_id' => $this->carrera->id,
            'padre_id' => $panol->id,
            'tipo' => 'mueble',
            'nombre' => 'Armario de Seguridad Blindado',
        ]);

        // Registrar insumo en Cajonera 1A
        $insumo = $this->inventarioService->registrarInsumo([
            'codigo_barras' => 'INS-RJ45-CAT6',
            'nombre' => 'Conector Macho RJ-45 Categoría 6 Blindado',
            'unidad' => 'unidad',
            'es_retornable' => false,
            'stock_actual' => 100,
            'stock_minimo' => 20,
            'ubicacion_id' => $cajon1->id,
        ]);

        $this->assertEquals($cajon1->id, $insumo->ubicacion_id);
        $this->assertEquals('Cajonera 1A', $insumo->ubicacion->nombre);

        // Modificar ubicación a Armario Blindado
        $insumoActualizado = $this->inventarioService->actualizarInsumo($insumo, [
            'ubicacion_id' => $mueble2->id,
        ]);

        $this->assertEquals($mueble2->id, $insumoActualizado->ubicacion_id);
        $this->assertEquals('Armario de Seguridad Blindado', $insumoActualizado->ubicacion->nombre);

        $this->assertDatabaseHas('insumos', [
            'id' => $insumo->id,
            'ubicacion_id' => $mueble2->id,
        ]);
    }
}
