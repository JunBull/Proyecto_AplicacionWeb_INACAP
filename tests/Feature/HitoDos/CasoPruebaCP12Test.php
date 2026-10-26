<?php

namespace Tests\Feature\HitoDos;

use App\Models\Carrera;
use App\Models\Equipo;
use App\Models\Insumo;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\BarcodeService;
use App\Services\EquipoService;
use App\Services\InventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CasoPruebaCP12Test extends TestCase
{
    use RefreshDatabase;

    protected Carrera $carrera;
    protected Ubicacion $ubicacion;
    protected User $panolero;
    protected BarcodeService $barcodeService;
    protected EquipoService $equipoService;
    protected InventarioService $inventarioService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->carrera = Carrera::create([
            'codigo' => 'INF-07',
            'nombre' => 'Ingeniería en Informática',
            'activa' => true,
        ]);

        $this->ubicacion = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Pañol Central',
            'tipo' => 'panol',
            'padre_id' => null,
        ]);

        $this->panolero = User::factory()->create([
            'email' => 'panolero.cp12@inacap.cl',
            'rol' => 'encargado_panol',
        ]);

        $this->barcodeService = app(BarcodeService::class);
        $this->equipoService = app(EquipoService::class);
        $this->inventarioService = app(InventarioService::class);
    }

    /**
     * CP-12: Generación de código de barras 1D estandarizado (CODE128) con formato institucional legible.
     */
    public function test_generacion_codigo_barras_lineal_code128_valido(): void
    {
        $codigo = 'EQ-INACAP-10023';
        $svg = $this->barcodeService->generarSvg($codigo, 60, 2);

        $this->assertNotEmpty($svg);
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);
        $this->assertStringContainsString('shape-rendering="crispEdges"', $svg);
        $this->assertStringContainsString('<rect', $svg);

        // Generar etiqueta HTML completa institucional
        $htmlEtiqueta = $this->barcodeService->generarEtiquetaHtml([
            'codigo' => $codigo,
            'nombre' => 'Tester de Cables de Red Fluke Networks MicroScanner2',
            'institucion' => 'INACAP · SEDE TEMUCO',
            'ubicacion' => 'Pañol Informática',
        ]);

        $this->assertStringContainsString('INACAP · SEDE TEMUCO', $htmlEtiqueta);
        $this->assertStringContainsString('Tester de Cables de Red Fluke Networks MicroScanner2', $htmlEtiqueta);
        $this->assertStringContainsString('*EQ-INACAP-10023*', $htmlEtiqueta);
        $this->assertStringContainsString('<svg', $htmlEtiqueta);
    }

    /**
     * CP-12 / SLA-07: Rendimiento en Generación de Etiquetas.
     * Generar un lote de hasta 20 etiquetas de código de barras en 10 segundos o menos.
     */
    public function test_cumplimiento_sla_07_lote_hasta_20_etiquetas_en_menos_de_10_segundos(): void
    {
        $items = [];
        for ($i = 1; $i <= 20; $i++) {
            $items[] = [
                'codigo' => sprintf('EQ-SLA07-%04d', $i),
                'nombre' => "Equipo de Laboratorio Especializado #{$i} - Telecomunicaciones",
                'institucion' => 'INACAP · SEDE TEMUCO',
                'ubicacion' => 'Laboratorio 301',
            ];
        }

        $inicio = microtime(true);
        $resultado = $this->barcodeService->generarLoteEtiquetas($items);
        $duracionSegundos = microtime(true) - $inicio;

        $this->assertEquals(20, $resultado['total']);
        $this->assertNotEmpty($resultado['html']);

        // Cumplimiento estricto del SLA-07: <= 10 segundos
        $this->assertLessThan(
            10.0,
            $duracionSegundos,
            "La generación del lote de 20 etiquetas demoró {$duracionSegundos}s, superando el límite del SLA-07 (10s)."
        );

        // Además verificamos que la métrica interna informe tiempo adecuado
        $this->assertLessThan(10000.0, $resultado['tiempo_ms']);
    }

    /**
     * CP-12: Lectura óptica mediante pistola captura el identificador y recupera el registro exacto.
     */
    public function test_lectura_optica_pistola_recupera_registro_exacto(): void
    {
        $equipo = $this->equipoService->registrarEquipo([
            'codigo_barras' => 'BAR-SCAN-EQ-777',
            'nombre' => 'Cámara de Documentos AverVision U50 - Sala Multimedia',
            'marca' => 'AVer',
            'modelo' => 'U50',
            'numero_serie' => 'AVER777888',
            'criticidad' => 'media',
            'ubicacion_id' => $this->ubicacion->id,
        ], $this->panolero);

        $insumo = $this->inventarioService->registrarInsumo([
            'codigo_barras' => 'BAR-SCAN-INS-888',
            'nombre' => 'Bobina de Cable Par Trenzado UTP 305 Metros',
            'unidad' => 'caja',
            'es_retornable' => false,
            'stock_actual' => 15,
            'stock_minimo' => 3,
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        // 1. Simulación de pistola escaneando código del equipo
        $recuperadoEquipo = $this->barcodeService->buscarPorCodigoBarras('BAR-SCAN-EQ-777');
        $this->assertNotNull($recuperadoEquipo);
        $this->assertEquals('equipo', $recuperadoEquipo['tipo']);
        $this->assertEquals($equipo->id, $recuperadoEquipo['item']->id);
        $this->assertEquals('Cámara de Documentos AverVision U50 - Sala Multimedia', $recuperadoEquipo['item']->nombre);

        // 2. Simulación de pistola escaneando código del insumo
        $recuperadoInsumo = $this->barcodeService->buscarPorCodigoBarras('BAR-SCAN-INS-888');
        $this->assertNotNull($recuperadoInsumo);
        $this->assertEquals('insumo', $recuperadoInsumo['tipo']);
        $this->assertEquals($insumo->id, $recuperadoInsumo['item']->id);
        $this->assertEquals('Bobina de Cable Par Trenzado UTP 305 Metros', $recuperadoInsumo['item']->nombre);

        // 3. Código no existente
        $noExiste = $this->barcodeService->buscarPorCodigoBarras('CODIGO-INEXISTENTE-999');
        $this->assertNull($noExiste);
    }
}
