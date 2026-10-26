<?php

namespace Tests\Feature\HitoTres;

use App\Jobs\DespacharCotizacionJob;
use App\Mail\SolicitudCotizacionMailable;
use App\Models\Carrera;
use App\Models\EnvioCotizacion;
use App\Models\Proveedor;
use App\Models\SolicitudCotizacion;
use App\Models\User;
use App\Services\CotizacionService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CasoPruebaCP17Test extends TestCase
{
    use RefreshDatabase;

    protected Carrera $carrera;

    protected User $panolero;

    protected CotizacionService $cotizacionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->carrera = Carrera::create([
            'codigo' => 'TEL-01',
            'nombre' => 'Ingeniería en Telecomunicaciones',
            'activa' => true,
        ]);

        $this->panolero = User::factory()->create([
            'email' => 'panolero.cp17@inacap.cl',
            'rol' => 'encargado_panol',
        ]);

        $this->cotizacionService = app(CotizacionService::class);
    }

    public function test_encola_despacho_masivo_de_correos_a_proveedores_seleccionados(): void
    {
        Queue::fake();

        $p1 = Proveedor::create([
            'razon_social' => 'Proveedor A Ltda.',
            'rut' => '76.111.111-1',
            'correo' => 'ventas@proveedora.cl',
            'tipo' => 'insumos',
            'activo' => true,
        ]);

        $p2 = Proveedor::create([
            'razon_social' => 'Proveedor B SpA',
            'rut' => '76.222.222-2',
            'correo' => 'ventas@proveedorb.cl',
            'tipo' => 'insumos',
            'activo' => true,
        ]);

        $solicitud = $this->cotizacionService->crearSolicitudCotizacion([
            'carrera_id' => $this->carrera->id,
            'descripcion' => '10 Cautines 60W y 20 Rollos de Estaño 60/40 para talleres semestrales.',
        ], $this->panolero);

        $envios = $this->cotizacionService->despacharSolicitudProveedores($solicitud, [$p1->id, $p2->id]);

        $this->assertCount(2, $envios);
        $this->assertEquals('enviada', $solicitud->fresh()->estado);

        // Verifica que se hayan encolado los jobs respectivos
        Queue::assertPushed(DespacharCotizacionJob::class, 2);

        // Verifica la creación de registros en la bitácora envios_cotizacion
        $this->assertDatabaseHas('envios_cotizacion', [
            'solicitud_cotizacion_id' => $solicitud->id,
            'proveedor_id' => $p1->id,
            'estado' => 'pendiente',
            'intentos' => 0,
        ]);

        $this->assertDatabaseHas('envios_cotizacion', [
            'solicitud_cotizacion_id' => $solicitud->id,
            'proveedor_id' => $p2->id,
            'estado' => 'pendiente',
            'intentos' => 0,
        ]);
    }

    public function test_procesamiento_exitoso_de_envio_actualiza_bitacora_y_marca_temporal(): void
    {
        Mail::fake();

        $proveedor = Proveedor::create([
            'razon_social' => 'Electrónica Central',
            'rut' => '76.333.333-3',
            'correo' => 'contacto@electronicacentral.cl',
            'tipo' => 'insumos',
            'activo' => true,
        ]);

        $solicitud = SolicitudCotizacion::create([
            'carrera_id' => $this->carrera->id,
            'creada_por' => $this->panolero->id,
            'descripcion' => 'Osciloscopios portátiles',
            'estado' => 'enviada',
            'fecha_creacion' => now(),
        ]);

        $envio = EnvioCotizacion::create([
            'solicitud_cotizacion_id' => $solicitud->id,
            'proveedor_id' => $proveedor->id,
            'estado' => 'pendiente',
            'intentos' => 0,
        ]);

        $resultado = $this->cotizacionService->procesarEnvioIndividual($envio->id);

        $this->assertEquals('enviado', $resultado->estado);
        $this->assertEquals(1, $resultado->intentos);
        $this->assertNotNull($resultado->enviado_en);
        $this->assertNull($resultado->error);

        Mail::assertSent(SolicitudCotizacionMailable::class, function ($mail) use ($proveedor) {
            return $mail->hasTo($proveedor->correo);
        });
    }

    public function test_manejo_controlado_de_falla_smtp_sin_caida_del_sistema(): void
    {
        Mail::shouldReceive('to')
            ->once()
            ->andThrow(new Exception('Error de conexión SMTP con el servidor de correo institucional'));

        $proveedor = Proveedor::create([
            'razon_social' => 'Insumos del Maule',
            'rut' => '76.444.444-4',
            'correo' => 'maule@insumos.cl',
            'tipo' => 'insumos',
            'activo' => true,
        ]);

        $solicitud = SolicitudCotizacion::create([
            'carrera_id' => $this->carrera->id,
            'creada_por' => $this->panolero->id,
            'descripcion' => 'Multímetros digitales',
            'estado' => 'enviada',
            'fecha_creacion' => now(),
        ]);

        $envio = EnvioCotizacion::create([
            'solicitud_cotizacion_id' => $solicitud->id,
            'proveedor_id' => $proveedor->id,
            'estado' => 'pendiente',
            'intentos' => 0,
        ]);

        // No debe lanzar excepción no controlada hacia arriba (PI-05, SLA-05)
        $resultado = $this->cotizacionService->procesarEnvioIndividual($envio->id);

        $this->assertEquals('fallido', $resultado->estado);
        $this->assertEquals(1, $resultado->intentos);
        $this->assertNull($resultado->enviado_en);
        $this->assertStringContainsString('SMTP', $resultado->error);
    }

    public function test_rechaza_solicitud_con_descripcion_vacia(): void
    {
        $this->expectException(ValidationException::class);

        $this->cotizacionService->crearSolicitudCotizacion([
            'carrera_id' => $this->carrera->id,
            'descripcion' => '   ',
        ], $this->panolero);
    }
}
