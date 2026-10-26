<?php

namespace Tests\Feature\HitoCinco;

use App\Models\BajaReemplazo;
use App\Models\Carrera;
use App\Models\Cotizacion;
use App\Models\Equipo;
use App\Models\Insumo;
use App\Models\Proveedor;
use App\Models\SolicitudCotizacion;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\EquipoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SeguridadPermisosOwaspTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $panolero;

    protected User $docente;

    protected Carrera $carrera;

    protected Ubicacion $ubicacion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'rol' => 'administrador',
            'activo' => true,
        ]);

        $this->panolero = User::factory()->create([
            'rol' => 'encargado_panol',
            'activo' => true,
        ]);

        $this->docente = User::factory()->create([
            'rol' => 'docente',
            'activo' => true,
        ]);

        $this->carrera = Carrera::create([
            'nombre' => 'Ingeniería en Informática',
            'codigo' => 'INF-01',
            'activa' => true,
        ]);

        $this->ubicacion = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Laboratorio Redes',
            'codigo' => 'LAB-RED',
            'tipo' => 'laboratorio',
        ]);
    }

    /**
     * OWASP A01: Aislamiento de privilegios mediante Policies y Middleware.
     */
    public function test_aislamiento_de_privilegios_docente_no_puede_crear_equipos_ni_aprobar_bajas(): void
    {
        // 1. Docente no puede crear equipos ni insumos
        $this->assertFalse(Gate::forUser($this->docente)->allows('create', Equipo::class));
        $this->assertFalse(Gate::forUser($this->docente)->allows('create', Insumo::class));

        // 2. Pañolero y Admin sí pueden crear equipos
        $this->assertTrue(Gate::forUser($this->panolero)->allows('create', Equipo::class));
        $this->assertTrue(Gate::forUser($this->admin)->allows('create', Equipo::class));

        // 3. Crear equipo de prueba
        $equipo = Equipo::create([
            'codigo_barras' => 'EQ-SEC-0001',
            'nombre' => 'Servidor Blade Virtualizado',
            'marca' => 'Dell',
            'modelo' => 'PowerEdge',
            'numero_serie' => 'SN-DEL-001',
            'criticidad' => 'alta',
            'estado' => 'disponible',
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        $baja = BajaReemplazo::create([
            'equipo_id' => $equipo->id,
            'propuesta_por' => $this->panolero->id,
            'tipo' => 'baja',
            'motivo' => 'Falla irreparable en placa madre y coste superior al 70%.',
            'fecha' => now()->toDateString(),
            'estado' => 'propuesta',
        ]);

        // 4. Pañolero puede proponer, pero solo Admin puede aprobar baja técnica (CP-07)
        $this->assertTrue(Gate::forUser($this->panolero)->allows('proponer', BajaReemplazo::class));
        $this->assertFalse(Gate::forUser($this->panolero)->allows('aprobar', $baja));
        $this->assertFalse(Gate::forUser($this->docente)->allows('aprobar', $baja));
        $this->assertTrue(Gate::forUser($this->admin)->allows('aprobar', $baja));
    }

    /**
     * OWASP A01: Exclusividad del Administrador para adjudicar cotizaciones (CP-18).
     */
    public function test_solo_administrador_puede_adjudicar_compras_y_autorizar_excepciones(): void
    {
        $proveedor = Proveedor::create([
            'rut' => '76111222-3',
            'razon_social' => 'Servicios TI SpA',
            'correo' => 'ventas@serviciosti.cl',
            'tipo' => 'insumos',
            'activo' => true,
        ]);

        $solicitud = SolicitudCotizacion::create([
            'carrera_id' => $this->carrera->id,
            'creada_por' => $this->panolero->id,
            'descripcion' => 'Cables UTP Cat6',
            'estado' => 'en_evaluacion',
        ]);

        $cotizacion = Cotizacion::create([
            'solicitud_cotizacion_id' => $solicitud->id,
            'proveedor_id' => $proveedor->id,
            'monto_total' => 250000,
            'fecha_recepcion' => now()->toDateString(),
        ]);

        // Docente y Pañolero no pueden adjudicar compras
        $this->assertFalse(Gate::forUser($this->docente)->allows('adjudicar', $cotizacion));
        $this->assertFalse(Gate::forUser($this->panolero)->allows('adjudicar', $cotizacion));
        $this->assertTrue(Gate::forUser($this->admin)->allows('adjudicar', $cotizacion));

        // Excepción de cuota mínima: solo Admin
        $this->assertFalse(Gate::forUser($this->panolero)->allows('autorizarExcepcion', Cotizacion::class));
        $this->assertTrue(Gate::forUser($this->admin)->allows('autorizarExcepcion', Cotizacion::class));
    }

    /**
     * OWASP A03: Protección contra Inyección SQL mediante consultas parametrizadas.
     */
    public function test_resistencia_inyeccion_sql_en_busquedas_y_filtros_de_inventario(): void
    {
        Insumo::create([
            'codigo_barras' => 'INS-SEC-01',
            'nombre' => 'Cable HDMI 2.0',
            'unidad' => 'unidad',
            'es_retornable' => true,
            'stock_actual' => 10,
            'stock_minimo' => 2,
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        $equipoService = app(EquipoService::class);

        // Vector SQLi típico intentando cerrar comillas y extraer información
        $payloads = [
            "' OR '1'='1",
            "'; DROP TABLE insumos; --",
            "1' UNION SELECT NULL, NULL, password, NULL FROM usuarios --",
            "admin'--",
            '" OR ""="',
        ];

        foreach ($payloads as $payload) {
            $resultadoEquipos = $equipoService->buscar($payload);
            $this->assertNotNull($resultadoEquipos);
            $this->assertCount(0, $resultadoEquipos);

            // Búsqueda en insumos usando Eloquent con bindings
            $resultadoInsumos = Insumo::where('nombre', 'LIKE', '%'.$payload.'%')->get();
            $this->assertNotNull($resultadoInsumos);
            $this->assertCount(0, $resultadoInsumos);
        }

        // La tabla insumos debe seguir existiendo e íntegra
        $this->assertDatabaseHas('insumos', ['codigo_barras' => 'INS-SEC-01']);
    }

    /**
     * OWASP A08: Validación estricta de extensiones y tamaños de archivos subidos.
     */
    public function test_validacion_estricta_de_extensiones_y_tamano_de_archivos(): void
    {
        $equipoService = app(EquipoService::class);

        // Intento de subir script malicioso como fotografía
        $archivoPeligroso = UploadedFile::fake()->create('exploit.php', 100, 'application/x-php');

        $this->expectException(ValidationException::class);

        $equipoService->registrarEquipo([
            'codigo_barras' => 'EQ-TEST-OWASP',
            'nombre' => 'Impresora Térmica de Etiquetas',
            'marca' => 'Zebra',
            'modelo' => 'ZD420',
            'numero_serie' => 'SN-OWASP-001',
            'criticidad' => 'media',
            'ubicacion_id' => $this->ubicacion->id,
            'fotografia' => $archivoPeligroso,
        ], $this->panolero);
    }

    /**
     * OWASP A08: Rechazo de archivos de imagen superiores a 5MB.
     */
    public function test_rechazo_de_archivo_fotografia_que_supera_limite_de_tamano(): void
    {
        $equipoService = app(EquipoService::class);

        // 6 MB supera el umbral institucional de 5 MB
        $archivoPesado = UploadedFile::fake()->create('foto_gigante.jpg', 6144, 'image/jpeg');

        try {
            $equipoService->registrarEquipo([
                'codigo_barras' => 'EQ-TEST-SIZE',
                'nombre' => 'Osciloscopio Digital de Laboratorio',
                'marca' => 'Tektronix',
                'modelo' => 'TBS1052B',
                'numero_serie' => 'SN-SIZE-001',
                'criticidad' => 'alta',
                'ubicacion_id' => $this->ubicacion->id,
                'fotografia' => $archivoPesado,
            ], $this->panolero);

            $this->fail('Se esperaba ValidationException por tamaño excesivo de archivo.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('fotografia', $e->errors());
            $this->assertStringContainsString('5 MB', $e->errors()['fotografia'][0]);
        }
    }

    /**
     * OWASP A05: Manejo seguro de errores sin divulgación de stack traces.
     */
    public function test_manejo_seguro_de_errores_en_modo_produccion_sin_divulgar_stack_traces(): void
    {
        config(['app.debug' => false]);

        $response = $this->get('/recurso-inexistente-para-test-404');
        $response->assertStatus(404);

        // No debe contener rutas de archivos internas de Windows/Linux ni trazas de código
        $contenido = $response->getContent();
        $this->assertStringNotContainsString('vendor\\laravel\\framework', $contenido);
        $this->assertStringNotContainsString('Stack trace:', $contenido);
        $this->assertStringNotContainsString('D:\\Proyecto_AplicacionWeb_INACAP', $contenido);
    }
}
