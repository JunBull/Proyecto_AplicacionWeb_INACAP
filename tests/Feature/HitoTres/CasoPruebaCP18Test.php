<?php

namespace Tests\Feature\HitoTres;

use App\Exceptions\SaldoInsuficienteException;
use App\Models\Adjudicacion;
use App\Models\Carrera;
use App\Models\Cotizacion;
use App\Models\Presupuesto;
use App\Models\Proveedor;
use App\Models\SolicitudCotizacion;
use App\Models\User;
use App\Services\CotizacionService;
use App\Services\PresupuestoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CasoPruebaCP18Test extends TestCase
{
    use RefreshDatabase;

    protected Carrera $carrera;

    protected User $administrador;

    protected User $panolero;

    protected CotizacionService $cotizacionService;

    protected PresupuestoService $presupuestoService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->carrera = Carrera::create([
            'codigo' => 'MEC-01',
            'nombre' => 'Ingeniería Mecánica',
            'activa' => true,
        ]);

        $this->administrador = User::factory()->create([
            'email' => 'admin.cp18@inacap.cl',
            'rol' => 'administrador',
        ]);

        $this->panolero = User::factory()->create([
            'email' => 'panolero.cp18@inacap.cl',
            'rol' => 'encargado_panol',
        ]);

        $this->cotizacionService = app(CotizacionService::class);
        $this->presupuestoService = app(PresupuestoService::class);

        // Presupuesto asignado suficiente para 2026
        $this->presupuestoService->obtenerOCrearPresupuesto($this->carrera, 2026, 5000000);
    }

    public function test_registra_cotizacion_y_respalda_documento_con_hash_sha256(): void
    {
        Storage::fake('local');

        $proveedor = Proveedor::create([
            'razon_social' => 'Herramientas del Sur SpA',
            'rut' => '76.100.100-1',
            'correo' => 'ventas@herramientas.cl',
            'tipo' => 'insumos',
            'activo' => true,
        ]);

        $solicitud = SolicitudCotizacion::create([
            'carrera_id' => $this->carrera->id,
            'creada_por' => $this->panolero->id,
            'descripcion' => 'Juego de llaves Allen y dinamométricas',
            'estado' => 'enviada',
            'fecha_creacion' => now(),
        ]);

        $archivo = UploadedFile::fake()->create('cotizacion_herramientas.pdf', 150, 'application/pdf');

        $cotizacion = $this->cotizacionService->registrarCotizacion(
            $solicitud,
            $proveedor,
            350000,
            '2026-10-26',
            $archivo,
            $this->panolero
        );

        $this->assertInstanceOf(Cotizacion::class, $cotizacion);
        $this->assertEquals(350000, (float) $cotizacion->monto_total);
        $this->assertEquals('en_evaluacion', $solicitud->fresh()->estado);

        // Verifica que se guardó el documento polimórfico y el hash SHA-256
        $this->assertDatabaseHas('documentos', [
            'tipo' => 'cotizacion',
            'documentable_type' => Cotizacion::class,
            'documentable_id' => $cotizacion->id,
            'nombre_original' => 'cotizacion_herramientas.pdf',
        ]);
    }

    public function test_permite_adjudicar_cuando_se_cumple_la_cuota_minima_de_cotizaciones(): void
    {
        $p1 = Proveedor::create([
            'razon_social' => 'Proveedor Uno',
            'rut' => '76.201.201-1',
            'correo' => 'p1@test.cl',
            'tipo' => 'insumos',
            'activo' => true,
        ]);

        $p2 = Proveedor::create([
            'razon_social' => 'Proveedor Dos',
            'rut' => '76.202.202-2',
            'correo' => 'p2@test.cl',
            'tipo' => 'insumos',
            'activo' => true,
        ]);

        $solicitud = SolicitudCotizacion::create([
            'carrera_id' => $this->carrera->id,
            'creada_por' => $this->panolero->id,
            'descripcion' => 'Cajas de herramientas',
            'estado' => 'en_evaluacion',
            'fecha_creacion' => now(),
        ]);

        $cot1 = Cotizacion::create([
            'solicitud_cotizacion_id' => $solicitud->id,
            'proveedor_id' => $p1->id,
            'monto_total' => 450000,
            'fecha_recepcion' => '2026-10-26',
        ]);

        $cot2 = Cotizacion::create([
            'solicitud_cotizacion_id' => $solicitud->id,
            'proveedor_id' => $p2->id,
            'monto_total' => 420000,
            'fecha_recepcion' => '2026-10-26',
        ]);

        // Total propuestas = 2 (cumple cuota mínima KPI-08 sin requerir excepción)
        $adjudicacion = $this->cotizacionService->adjudicarCotizacion($cot2, $this->administrador);

        $this->assertInstanceOf(Adjudicacion::class, $adjudicacion);
        $this->assertFalse($adjudicacion->excepcion_autorizada);
        $this->assertEquals('adjudicada', $solicitud->fresh()->estado);
    }

    public function test_rechaza_adjudicacion_con_cuota_insuficiente_sin_excepcion_autorizada(): void
    {
        $proveedor = Proveedor::create([
            'razon_social' => 'Proveedor Único',
            'rut' => '76.301.301-1',
            'correo' => 'unico@test.cl',
            'tipo' => 'insumos',
            'activo' => true,
        ]);

        $solicitud = SolicitudCotizacion::create([
            'carrera_id' => $this->carrera->id,
            'creada_por' => $this->panolero->id,
            'descripcion' => 'Tornos mecánicos',
            'estado' => 'en_evaluacion',
            'fecha_creacion' => now(),
        ]);

        $cot = Cotizacion::create([
            'solicitud_cotizacion_id' => $solicitud->id,
            'proveedor_id' => $proveedor->id,
            'monto_total' => 1200000,
            'fecha_recepcion' => '2026-10-26',
        ]);

        $this->expectException(ValidationException::class);

        // Intenta adjudicar con 1 sola cotización sin marcar excepción
        $this->cotizacionService->adjudicarCotizacion($cot, $this->administrador, false);
    }

    public function test_rechaza_adjudicacion_por_excepcion_si_falta_la_justificacion_obligatoria(): void
    {
        $proveedor = Proveedor::create([
            'razon_social' => 'Proveedor Especialista',
            'rut' => '76.401.401-1',
            'correo' => 'especialista@test.cl',
            'tipo' => 'insumos',
            'activo' => true,
        ]);

        $solicitud = SolicitudCotizacion::create([
            'carrera_id' => $this->carrera->id,
            'creada_por' => $this->panolero->id,
            'descripcion' => 'Calibrador láser de alta precisión',
            'estado' => 'en_evaluacion',
            'fecha_creacion' => now(),
        ]);

        $cot = Cotizacion::create([
            'solicitud_cotizacion_id' => $solicitud->id,
            'proveedor_id' => $proveedor->id,
            'monto_total' => 800000,
            'fecha_recepcion' => '2026-10-26',
        ]);

        $this->expectException(ValidationException::class);

        // Marca excepción pero no provee justificación (infracción KPI-08)
        $this->cotizacionService->adjudicarCotizacion($cot, $this->administrador, true, '');
    }

    public function test_aprueba_adjudicacion_por_excepcion_con_justificacion_valida(): void
    {
        $proveedor = Proveedor::create([
            'razon_social' => 'Representante Exclusivo Haas Chile',
            'rut' => '76.501.501-1',
            'correo' => 'ventas@haas.cl',
            'tipo' => 'insumos',
            'activo' => true,
        ]);

        $solicitud = SolicitudCotizacion::create([
            'carrera_id' => $this->carrera->id,
            'creada_por' => $this->panolero->id,
            'descripcion' => 'Repuestos certificados para centro de mecanizado CNC',
            'estado' => 'en_evaluacion',
            'fecha_creacion' => now(),
        ]);

        $cot = Cotizacion::create([
            'solicitud_cotizacion_id' => $solicitud->id,
            'proveedor_id' => $proveedor->id,
            'monto_total' => 950000,
            'fecha_recepcion' => '2026-10-26',
        ]);

        $justificacion = 'Proveedor único en Chile autorizado por el fabricante para repuestos CNC certificados Haas.';

        $adjudicacion = $this->cotizacionService->adjudicarCotizacion(
            $cot,
            $this->administrador,
            true,
            $justificacion
        );

        $this->assertInstanceOf(Adjudicacion::class, $adjudicacion);
        $this->assertTrue($adjudicacion->excepcion_autorizada);
        $this->assertEquals($justificacion, $adjudicacion->justificacion);
    }

    public function test_rechaza_adjudicacion_si_presupuesto_de_carrera_es_insuficiente(): void
    {
        // Ajustamos saldo disponible a solo $100.000
        $presupuesto = Presupuesto::where('carrera_id', $this->carrera->id)->where('anio', 2026)->first();
        $presupuesto->update(['saldo_actual' => 100000]);

        $p1 = Proveedor::create(['razon_social' => 'P1', 'rut' => '76.601.601-1', 'correo' => 'p1@x.cl', 'tipo' => 'insumos', 'activo' => true]);
        $p2 = Proveedor::create(['razon_social' => 'P2', 'rut' => '76.602.602-2', 'correo' => 'p2@x.cl', 'tipo' => 'insumos', 'activo' => true]);

        $solicitud = SolicitudCotizacion::create([
            'carrera_id' => $this->carrera->id,
            'creada_por' => $this->panolero->id,
            'descripcion' => 'Insumos caros',
            'estado' => 'en_evaluacion',
            'fecha_creacion' => now(),
        ]);

        $cot1 = Cotizacion::create(['solicitud_cotizacion_id' => $solicitud->id, 'proveedor_id' => $p1->id, 'monto_total' => 500000, 'fecha_recepcion' => '2026-10-26']);
        $cot2 = Cotizacion::create(['solicitud_cotizacion_id' => $solicitud->id, 'proveedor_id' => $p2->id, 'monto_total' => 450000, 'fecha_recepcion' => '2026-10-26']);

        $this->expectException(SaldoInsuficienteException::class);

        // Se intenta adjudicar cot2 ($450.000) teniendo solo $100.000 de saldo
        $this->cotizacionService->adjudicarCotizacion($cot2, $this->administrador);
    }
}
