<?php

namespace Tests\Feature\HitoTres;

use App\Models\Carrera;
use App\Models\Insumo;
use App\Models\MovimientoInventario;
use App\Models\MovimientoPresupuesto;
use App\Models\Presupuesto;
use App\Models\Ubicacion;
use App\Services\OcrService;
use App\Services\PresupuestoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CasoPruebaCP08Test extends TestCase
{
    use RefreshDatabase;

    protected OcrService $ocrService;

    protected Carrera $carrera;

    protected Ubicacion $ubicacion;

    protected Insumo $insumo;

    protected Presupuesto $presupuesto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ocrService = app(OcrService::class);

        $this->carrera = Carrera::create([
            'codigo' => 'ELC-01',
            'nombre' => 'Electricidad y Eficiencia Energética',
            'activa' => true,
        ]);

        $this->ubicacion = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Taller Eléctrico Central',
            'tipo' => 'panol',
        ]);

        $this->insumo = Insumo::create([
            'codigo_barras' => 'INS-TEST-001',
            'nombre' => 'Disyuntor Termomagnético 16A',
            'unidad' => 'unidad',
            'es_retornable' => false,
            'stock_actual' => 10,
            'stock_minimo' => 5,
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        $this->presupuesto = app(PresupuestoService::class)->obtenerOCrearPresupuesto($this->carrera, 2026, 1000000);
    }

    public function test_extrae_datos_correctamente_de_documento_estructurado(): void
    {
        $contenidoFactura = json_encode([
            'numero_documento' => 'FAC-98451',
            'proveedor_rut' => '76.888.999-0',
            'proveedor_nombre' => 'Distribuidora Eléctrica Temuco',
            'fecha' => '2026-10-26',
            'confianza' => 0.98,
            'monto_total' => 150000,
            'items' => [
                [
                    'descripcion' => 'Disyuntor Termomagnético 16A Legrand',
                    'cantidad' => 15,
                    'costo_unitario' => 10000,
                    'impuesto' => 0,
                ],
            ],
        ]);

        $archivo = UploadedFile::fake()->createWithContent('factura_digital.json', $contenidoFactura);

        $resultado = $this->ocrService->extraerDatos($archivo);

        $this->assertTrue($resultado['ocr_exitoso']);
        $this->assertEquals('FAC-98451', $resultado['numero_documento']);
        $this->assertEquals('76.888.999-0', $resultado['proveedor_rut']);
        $this->assertCount(1, $resultado['items']);
        $this->assertEquals(15, $resultado['items'][0]['cantidad']);
        $this->assertEquals(10000, $resultado['items'][0]['costo_unitario']);
        $this->assertGreaterThanOrEqual(0.90, $resultado['confianza']);
    }

    public function test_manejo_tolerante_de_documento_danado_o_calidad_deficiente(): void
    {
        // Documento con texto ilegible o dañado
        $contenidoIlegible = '@@#$$%^^&*()__++===??? ILEGIBLE ERROR ESCANEO MANCHA DE TINTA';
        $archivo = UploadedFile::fake()->createWithContent('factura_danada.txt', $contenidoIlegible);

        $resultado = $this->ocrService->extraerDatos($archivo);

        // No debe lanzar excepción ni error 500 (PI-05, SLA-04)
        $this->assertFalse($resultado['ocr_exitoso']);
        $this->assertNotNull($resultado['error']);
        $this->assertEmpty($resultado['items']);
        $this->assertLessThan(0.50, $resultado['confianza']);
    }

    public function test_regla_innegociable_la_extraccion_ocr_jamas_actualiza_stock_ni_presupuesto(): void
    {
        $saldoInicial = (float) $this->presupuesto->fresh()->saldo_actual;
        $stockInicial = (float) $this->insumo->fresh()->stock_actual;

        $contenido = json_encode([
            'numero_documento' => 'FAC-55555',
            'monto_total' => 200000,
            'items' => [
                ['descripcion' => 'Disyuntor Termomagnético 16A', 'cantidad' => 20, 'costo_unitario' => 10000],
            ],
        ]);
        $archivo = UploadedFile::fake()->createWithContent('factura_valida.json', $contenido);

        // Se ejecuta la extracción OCR
        $resultado = $this->ocrService->extraerDatos($archivo);
        $this->assertTrue($resultado['ocr_exitoso']);

        // Comprobación estricta de invariantes: stock y presupuesto intactos
        $this->assertEquals($saldoInicial, (float) $this->presupuesto->fresh()->saldo_actual);
        $this->assertEquals($stockInicial, (float) $this->insumo->fresh()->stock_actual);
        $this->assertEquals(0, MovimientoPresupuesto::count());
        $this->assertEquals(0, MovimientoInventario::count());
    }

    public function test_extrae_datos_mediante_regex_en_texto_plano_de_factura(): void
    {
        $textoFactura = <<<'TXT'
        FACTURA ELECTRONICA N°: 88412
        R.U.T.: 76.555.444-3
        EMISION: 2026-10-26
        DETALLE DE PRODUCTOS:
        CABLE UNIPOLAR 2.5MM ROJO | 100 | 450
        INTERRUPTOR TERMICO 25A | 10 | 12000
        TOTAL: 165000
        TXT;

        $archivo = UploadedFile::fake()->createWithContent('factura_escaneada.txt', $textoFactura);

        $resultado = $this->ocrService->extraerDatos($archivo);

        $this->assertTrue($resultado['ocr_exitoso']);
        $this->assertEquals('88412', $resultado['numero_documento']);
        $this->assertEquals('76.555.444-3', $resultado['proveedor_rut']);
        $this->assertCount(2, $resultado['items']);
        $this->assertEquals(100, $resultado['items'][0]['cantidad']);
        $this->assertEquals(450, $resultado['items'][0]['costo_unitario']);
    }
}
