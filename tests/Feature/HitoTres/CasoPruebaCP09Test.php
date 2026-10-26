<?php

namespace Tests\Feature\HitoTres;

use App\Models\Carrera;
use App\Models\Compra;
use App\Models\Insumo;
use App\Models\Proveedor;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\CompraService;
use App\Services\OcrService;
use App\Services\PresupuestoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CasoPruebaCP09Test extends TestCase
{
    use RefreshDatabase;

    protected CompraService $compraService;

    protected OcrService $ocrService;

    protected PresupuestoService $presupuestoService;

    protected Carrera $carrera;

    protected Ubicacion $ubicacion;

    protected Proveedor $proveedor;

    protected Insumo $insumo;

    protected User $panolero;

    protected function setUp(): void
    {
        parent::setUp();

        $this->compraService = app(CompraService::class);
        $this->ocrService = app(OcrService::class);
        $this->presupuestoService = app(PresupuestoService::class);

        $this->carrera = Carrera::create([
            'codigo' => 'AGR-01',
            'nombre' => 'Ingeniería Agrícola',
            'activa' => true,
        ]);

        $this->ubicacion = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Bodega Insumos Agrícolas',
            'tipo' => 'panol',
        ]);

        $this->proveedor = Proveedor::create([
            'razon_social' => 'Agroquímicos y Riego Araucanía SpA',
            'rut' => '76.777.888-9',
            'correo' => 'ventas@agroriego.cl',
            'tipo' => 'insumos',
            'activo' => true,
        ]);

        $this->insumo = Insumo::create([
            'codigo_barras' => 'INS-AGR-001',
            'nombre' => 'Aspersor de Impacto 1/2 pulgada',
            'unidad' => 'unidad',
            'es_retornable' => false,
            'stock_actual' => 5,
            'stock_minimo' => 10,
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        $this->panolero = User::factory()->create([
            'email' => 'panolero.cp09@inacap.cl',
            'rol' => 'encargado_panol',
        ]);

        // Presupuesto 2026: $2.000.000
        $this->presupuestoService->obtenerOCrearPresupuesto($this->carrera, 2026, 2000000);
    }

    public function test_persiste_valores_corregidos_por_el_panolero_y_confirma_ingreso_al_inventario(): void
    {
        Storage::fake('local');

        // Paso A: Simular lectura OCR que leyó erróneamente 50 unidades a $15.000
        $contenidoOcr = json_encode([
            'numero_documento' => 'FAC-99120',
            'proveedor_rut' => $this->proveedor->rut,
            'fecha' => '2026-10-26',
            'items' => [
                [
                    'descripcion' => 'Aspersor de Impacto 1/2 pulgada (Lectura Dudosa)',
                    'cantidad' => 50,
                    'costo_unitario' => 15000,
                ],
            ],
        ]);
        $archivo = UploadedFile::fake()->createWithContent('factura_proveedor.json', $contenidoOcr);

        $datosOcr = $this->ocrService->extraerDatos($archivo);
        $this->assertTrue($datosOcr['ocr_exitoso']);

        // Paso B: El pañolero revisa físicamente el mesón de pañol y corrige los datos:
        // En realidad llegaron 25 unidades a un costo unitario negociado de $12.000
        $itemsCorregidos = [
            [
                'insumo_id' => $this->insumo->id,
                'descripcion' => 'Aspersor de Impacto 1/2 pulgada (Verificado en pañol)',
                'cantidad' => 25.0,
                'costo_unitario' => 12000.0,
                'impuesto' => 0.0,
            ],
        ];

        // Paso C: El pañolero confirma el ingreso
        $compra = $this->compraService->registrarCompraEIngreso(
            [
                'proveedor_id' => $this->proveedor->id,
                'carrera_id' => $this->carrera->id,
                'numero_documento' => 'FAC-99120',
                'fecha' => '2026-10-26',
                'tipo_documento' => 'factura',
            ],
            $itemsCorregidos,
            $this->panolero,
            $archivo
        );

        $this->assertInstanceOf(Compra::class, $compra);
        $this->assertEquals(300000, (float) $compra->monto_total); // 25 * 12.000 = 300.000

        // 1. Insumo actualizado con la cantidad corregida: 5 inicial + 25 = 30
        $this->assertEquals(30, (float) $this->insumo->fresh()->stock_actual);

        // 2. Movimiento en Kardex
        $this->assertDatabaseHas('movimientos_inventario', [
            'insumo_id' => $this->insumo->id,
            'compra_id' => $compra->id,
            'usuario_id' => $this->panolero->id,
            'tipo' => 'ingreso_compra',
            'cantidad' => 25.0,
        ]);

        // 3. Descuento presupuestario exacto: 2.000.000 - 300.000 = 1.700.000
        $this->assertEquals(1700000, (float) $this->presupuestoService->consultarSaldo($this->carrera, 2026));

        // 4. Movimiento de presupuesto
        $this->assertDatabaseHas('movimientos_presupuesto', [
            'compra_id' => $compra->id,
            'usuario_id' => $this->panolero->id,
            'tipo' => 'cargo',
            'monto' => 300000,
        ]);

        // 5. Archivo guardado con hash SHA-256
        $this->assertDatabaseHas('documentos', [
            'documentable_type' => Compra::class,
            'documentable_id' => $compra->id,
            'tipo' => 'factura',
            'nombre_original' => 'factura_proveedor.json',
        ]);
    }
}
