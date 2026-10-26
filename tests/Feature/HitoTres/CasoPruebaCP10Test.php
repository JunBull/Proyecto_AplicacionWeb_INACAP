<?php

namespace Tests\Feature\HitoTres;

use App\Exceptions\DocumentoDuplicadoException;
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

class CasoPruebaCP10Test extends TestCase
{
    use RefreshDatabase;

    protected CompraService $compraService;

    protected OcrService $ocrService;

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

        $this->carrera = Carrera::create([
            'codigo' => 'MIN-01',
            'nombre' => 'Ingeniería en Minas',
            'activa' => true,
        ]);

        $this->ubicacion = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Laboratorio de Mineralogía',
            'tipo' => 'laboratorio',
        ]);

        $this->proveedor = Proveedor::create([
            'razon_social' => 'Equipamiento Minero del Pacífico SpA',
            'rut' => '76.999.111-2',
            'correo' => 'ventas@equipamientominero.cl',
            'tipo' => 'insumos',
            'activo' => true,
        ]);

        $this->insumo = Insumo::create([
            'codigo_barras' => 'INS-MIN-001',
            'nombre' => 'Tamiz Geológico Malla 200',
            'unidad' => 'unidad',
            'es_retornable' => true,
            'stock_actual' => 10,
            'stock_minimo' => 2,
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        $this->panolero = User::factory()->create([
            'email' => 'panolero.cp10@inacap.cl',
            'rol' => 'encargado_panol',
        ]);

        app(PresupuestoService::class)->obtenerOCrearPresupuesto($this->carrera, 2026, 5000000);
    }

    public function test_bloquea_reprocesamiento_de_documento_con_mismo_hash_sha256(): void
    {
        Storage::fake('local');

        $contenidoArchivo = "FACTURA: N-5544\nPROVEEDOR: Equipamiento Minero\nTOTAL: 250000\nTAMIZ: 2 x 125000";
        $archivoOriginal = UploadedFile::fake()->createWithContent('factura_original.txt', $contenidoArchivo);

        // Primer ingreso exitoso
        $compra1 = $this->compraService->registrarCompraEIngreso(
            [
                'proveedor_id' => $this->proveedor->id,
                'carrera_id' => $this->carrera->id,
                'numero_documento' => 'N-5544',
                'fecha' => '2026-10-26',
            ],
            [
                [
                    'insumo_id' => $this->insumo->id,
                    'descripcion' => 'Tamiz Geológico Malla 200',
                    'cantidad' => 2,
                    'costo_unitario' => 125000,
                ],
            ],
            $this->panolero,
            $archivoOriginal
        );

        $this->assertInstanceOf(Compra::class, $compra1);

        // Intento de reingresar el mismo archivo idéntico (mismo hash SHA-256)
        $archivoDuplicado = UploadedFile::fake()->createWithContent('factura_reenviada.txt', $contenidoArchivo);

        $this->expectException(DocumentoDuplicadoException::class);
        $this->expectExceptionMessage('Documento duplicado');

        $this->compraService->registrarCompraEIngreso(
            [
                'proveedor_id' => $this->proveedor->id,
                'carrera_id' => $this->carrera->id,
                'numero_documento' => 'N-5544-OTRO',
                'fecha' => '2026-10-26',
            ],
            [
                [
                    'insumo_id' => $this->insumo->id,
                    'descripcion' => 'Tamiz Geológico Malla 200',
                    'cantidad' => 2,
                    'costo_unitario' => 125000,
                ],
            ],
            $this->panolero,
            $archivoDuplicado
        );
    }

    public function test_bloquea_duplicidad_por_mismo_proveedor_y_numero_de_documento(): void
    {
        Storage::fake('local');

        // Registro inicial de compra con folio F-8888
        $this->compraService->registrarCompraEIngreso(
            [
                'proveedor_id' => $this->proveedor->id,
                'carrera_id' => $this->carrera->id,
                'numero_documento' => 'F-8888',
                'fecha' => '2026-10-26',
            ],
            [
                [
                    'insumo_id' => $this->insumo->id,
                    'descripcion' => 'Tamiz Geológico',
                    'cantidad' => 1,
                    'costo_unitario' => 100000,
                ],
            ],
            $this->panolero
        );

        $this->expectException(DocumentoDuplicadoException::class);
        $this->expectExceptionMessage('Compra ya registrada');

        // Intento de registrar nuevamente la compra F-8888 para el mismo proveedor (evitando duplicar costos)
        $this->compraService->registrarCompraEIngreso(
            [
                'proveedor_id' => $this->proveedor->id,
                'carrera_id' => $this->carrera->id,
                'numero_documento' => 'F-8888',
                'fecha' => '2026-10-26',
            ],
            [
                [
                    'insumo_id' => $this->insumo->id,
                    'descripcion' => 'Tamiz Geológico',
                    'cantidad' => 1,
                    'costo_unitario' => 100000,
                ],
            ],
            $this->panolero
        );
    }
}
