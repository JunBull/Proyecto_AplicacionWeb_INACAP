<?php

namespace Tests\Feature\HitoDos;

use App\Models\BajaReemplazo;
use App\Models\Carrera;
use App\Models\Documento;
use App\Models\Equipo;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\BajaReemplazoService;
use App\Services\EquipoService;
use App\Services\FallaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class CasoPruebaCP07Test extends TestCase
{
    use RefreshDatabase;

    protected Carrera $carrera;
    protected Ubicacion $ubicacion;
    protected User $panolero;
    protected User $administrador;
    protected User $docente;
    protected EquipoService $equipoService;
    protected FallaService $fallaService;
    protected BajaReemplazoService $bajaService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->carrera = Carrera::create([
            'codigo' => 'INF-05',
            'nombre' => 'Ingeniería en Informática',
            'activa' => true,
        ]);

        $this->ubicacion = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Taller de Ensamblaje y Mantenimiento',
            'tipo' => 'laboratorio',
            'padre_id' => null,
        ]);

        $this->panolero = User::factory()->create([
            'email' => 'panolero.cp07@inacap.cl',
            'rol' => 'encargado_panol',
        ]);

        $this->administrador = User::factory()->create([
            'email' => 'admin.cp07@inacap.cl',
            'rol' => 'administrador',
        ]);

        $this->docente = User::factory()->create([
            'email' => 'docente.cp07@inacap.cl',
            'rol' => 'docente',
        ]);

        $this->equipoService = app(EquipoService::class);
        $this->fallaService = app(FallaService::class);
        $this->bajaService = app(BajaReemplazoService::class);
    }

    /**
     * CP-07: Pañolero formaliza propuesta de baja o reemplazo con sustento de costos e informes adjuntos.
     */
    public function test_propuesta_baja_con_sustento_y_adjuntos_tecnicos(): void
    {
        Storage::fake('public');

        $equipo = $this->equipoService->registrarEquipo([
            'codigo_barras' => 'EQ-BAJA-001',
            'nombre' => 'Estación de Trabajo HP Z2 Tower G5 Workstation',
            'marca' => 'HP',
            'modelo' => 'Z2 Tower G5',
            'numero_serie' => 'CZ21400ABC',
            'criticidad' => 'alta',
            'ubicacion_id' => $this->ubicacion->id,
        ], $this->panolero);

        $falla = $this->fallaService->reportarFalla($equipo, $this->panolero, 'Fallo catastrófico en microprocesador Xeon y VRM');

        $informe = UploadedFile::fake()->create('informe_diagnostico_hp.pdf', 250, 'application/pdf');
        $cotizacion = UploadedFile::fake()->create('cotizacion_reemplazo_hp.pdf', 180, 'application/pdf');

        $motivo = 'La reparación presupuestada en cotización oficial asciende a $1.450.000 CLP, mientras que adquirir una estación de nueva generación equivalente con 3 años de garantía asciende a $1.390.000 CLP. No es viable económicamente reparar el activo.';

        $propuesta = $this->bajaService->proponerBajaOReemplazo(
            $equipo,
            $this->panolero,
            'reemplazo',
            $motivo,
            $falla->id,
            [$informe, $cotizacion]
        );

        $this->assertInstanceOf(BajaReemplazo::class, $propuesta);
        $this->assertEquals('propuesta', $propuesta->estado);
        $this->assertEquals('reemplazo', $propuesta->tipo);
        $this->assertEquals($this->panolero->id, $propuesta->propuesta_por);
        $this->assertNull($propuesta->aprobada_por);

        // Verifica que los documentos adjuntos queden respaldados en la tabla polimórfica 'documentos'
        $this->assertDatabaseHas('documentos', [
            'documentable_type' => BajaReemplazo::class,
            'documentable_id' => $propuesta->id,
            'nombre_original' => 'informe_diagnostico_hp.pdf',
        ]);

        $this->assertDatabaseHas('documentos', [
            'documentable_type' => BajaReemplazo::class,
            'documentable_id' => $propuesta->id,
            'nombre_original' => 'cotizacion_reemplazo_hp.pdf',
        ]);

        // El equipo debe pasar a estado pendiente_resolucion
        $equipo->refresh();
        $this->assertEquals('pendiente_resolucion', $equipo->estado);
    }

    /**
     * CP-07: Administrador aprueba la baja e inhabilita de forma definitiva al equipo para préstamos.
     */
    public function test_administrador_aprueba_baja_e_inhabilita_definitivamente_para_prestamos(): void
    {
        $equipo = $this->equipoService->registrarEquipo([
            'codigo_barras' => 'EQ-BAJA-002',
            'nombre' => 'Servidor de Almacenamiento NAS Synology RackStation',
            'marca' => 'Synology',
            'modelo' => 'RS2421+',
            'numero_serie' => 'SYN998811',
            'criticidad' => 'media',
            'ubicacion_id' => $this->ubicacion->id,
        ], $this->panolero);

        $propuesta = $this->bajaService->proponerBajaOReemplazo(
            $equipo,
            $this->panolero,
            'baja',
            'Doble fallo de controladora SAS e imposibilidad de obtener repuestos de fabricante descontinuado.'
        );

        $this->bajaService->resolverPropuesta(
            $propuesta,
            $this->administrador,
            true,
            'Baja aprobada conforme al informe técnico adjunto. Proceder con desincorporación contable.'
        );

        $propuesta->refresh();
        $this->assertEquals('aprobada', $propuesta->estado);
        $this->assertEquals($this->administrador->id, $propuesta->aprobada_por);

        $equipo->refresh();
        $this->assertEquals('dado_de_baja', $equipo->estado);

        // El equipo debe quedar inhabilitado definitivamente para préstamos
        $this->assertFalse(
            $this->bajaService->equipoHabilitadoParaPrestamo($equipo),
            'Un equipo formalmente dado de baja debe quedar inhabilitado de forma definitiva para préstamos.'
        );
    }

    /**
     * CP-07: Un rol no autorizado (docente o pañolero) no puede resolver/aprobar una baja.
     */
    public function test_usuario_no_administrador_no_puede_aprobar_baja(): void
    {
        $equipo = $this->equipoService->registrarEquipo([
            'codigo_barras' => 'EQ-BAJA-003',
            'nombre' => 'Switch Industrial Moxa EtherDevice EDS-408A',
            'marca' => 'Moxa',
            'modelo' => 'EDS-408A',
            'numero_serie' => 'MOX1234567',
            'criticidad' => 'media',
            'ubicacion_id' => $this->ubicacion->id,
        ], $this->panolero);

        $propuesta = $this->bajaService->proponerBajaOReemplazo(
            $equipo,
            $this->panolero,
            'baja',
            'Sobrevoltaje en fuente con daño total.'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->bajaService->resolverPropuesta($propuesta, $this->docente, true);
    }
}
