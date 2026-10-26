<?php

namespace Tests\Feature\HitoCinco;

use App\Models\Carrera;
use App\Models\EnvioCotizacion;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\CotizacionService;
use App\Services\OcrService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CasoIntegracionPI05Test extends TestCase
{
    use RefreshDatabase;

    protected User $panolero;

    protected Carrera $carrera;

    protected Proveedor $proveedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->panolero = User::factory()->create([
            'rol' => 'encargado_panol',
            'activo' => true,
        ]);

        $this->carrera = Carrera::create([
            'nombre' => 'Ingeniería en Informática',
            'codigo' => 'INF-01',
            'activa' => true,
        ]);

        $this->proveedor = Proveedor::create([
            'rut' => '76999888-1',
            'razon_social' => 'Conectividad Austral Ltda.',
            'correo' => 'ventas@conectividadaustral.cl',
            'tipo' => 'insumos',
            'activo' => true,
        ]);
    }

    /**
     * PI-05: Manejo tolerante ante falla de OCR permitiendo digitación manual asistida.
     */
    public function test_pi05_falla_de_servicio_ocr_es_manejada_sin_caida_permitiendo_completado_manual(): void
    {
        $ocrService = app(OcrService::class);

        // Archivo que no contiene texto estructurado reconocible (falla o documento dañado)
        $archivoDanado = UploadedFile::fake()->create('factura_corrupta.pdf', 50, 'application/pdf');

        $resultado = $ocrService->extraerDatos($archivoDanado);

        // El sistema responde de manera controlada sin lanzar excepciones fatales 500
        $this->assertIsArray($resultado);
        $this->assertFalse($resultado['ocr_exitoso']);
        $this->assertLessThanOrEqual(0.40, $resultado['confianza']);
        $this->assertNotNull($resultado['error']);
        $this->assertEmpty($resultado['items']);

        // Se verifica que la falla del OCR no altere ni corrompa el inventario ni el presupuesto
        $this->assertDatabaseCount('compras', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    /**
     * PI-05: Falla de red en servidor SMTP no interrumpe el flujo ni duplica transacciones.
     */
    public function test_pi05_falla_de_red_smtp_conserva_solicitud_y_registra_en_cola_para_reintento(): void
    {
        $cotizacionService = app(CotizacionService::class);

        // 1. Crear solicitud de cotización
        $solicitud = $cotizacionService->crearSolicitudCotizacion([
            'carrera_id' => $this->carrera->id,
            'descripcion' => 'Switches Gigabit 24 puertos para laboratorio de redes',
        ], $this->panolero);

        $this->assertDatabaseHas('solicitudes_cotizacion', [
            'id' => $solicitud->id,
            'estado' => 'borrador',
        ]);

        // 2. Registrar envío pendiente en bitácora
        $envio = EnvioCotizacion::create([
            'solicitud_cotizacion_id' => $solicitud->id,
            'proveedor_id' => $this->proveedor->id,
            'estado' => 'pendiente',
            'intentos' => 0,
        ]);

        // 3. Simular interrupción abrupta de red SMTP
        Mail::shouldReceive('to')
            ->once()
            ->andThrow(new Exception('SMTP Connection timed out (110): Network unreachable'));

        // 4. Ejecutar el procesamiento: debe capturar la falla sin causar caída
        $resultado = $cotizacionService->procesarEnvioIndividual($envio->id);

        // 5. Se verifica que la solicitud se conserva íntegramente
        $this->assertEquals('fallido', $resultado->estado);
        $this->assertEquals(1, $resultado->intentos);
        $this->assertNull($resultado->enviado_en);
        $this->assertStringContainsString('SMTP Connection timed out', $resultado->error);

        // La solicitud en BD sigue activa para posterior reintento
        $this->assertDatabaseHas('solicitudes_cotizacion', [
            'id' => $solicitud->id,
        ]);
    }
}
