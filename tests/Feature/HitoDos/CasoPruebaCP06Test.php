<?php

namespace Tests\Feature\HitoDos;

use App\Models\Carrera;
use App\Models\Equipo;
use App\Models\Falla;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\EquipoService;
use App\Services\FallaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CasoPruebaCP06Test extends TestCase
{
    use RefreshDatabase;

    protected Carrera $carrera;
    protected Ubicacion $ubicacion;
    protected User $docente;
    protected User $panolero;
    protected EquipoService $equipoService;
    protected FallaService $fallaService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->carrera = Carrera::create([
            'codigo' => 'INF-04',
            'nombre' => 'Ingeniería en Ciberseguridad',
            'activa' => true,
        ]);

        $this->ubicacion = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Laboratorio de Seguridad Ofensiva',
            'tipo' => 'laboratorio',
            'padre_id' => null,
        ]);

        $this->docente = User::factory()->create([
            'email' => 'docente.cp06@inacap.cl',
            'rol' => 'docente',
        ]);

        $this->panolero = User::factory()->create([
            'email' => 'panolero.cp06@inacap.cl',
            'rol' => 'encargado_panol',
        ]);

        $this->equipoService = app(EquipoService::class);
        $this->fallaService = app(FallaService::class);
    }

    /**
     * CP-06: Docente reporta falla en un activo, vinculando informante, hora y cambiando preventivamente el estado del equipo.
     */
    public function test_docente_reporta_falla_cambio_preventivo_estado_equipo(): void
    {
        $equipo = $this->equipoService->registrarEquipo([
            'codigo_barras' => 'EQ-FALLA-001',
            'nombre' => 'Firewall Perimetral Fortinet FortiGate 60F',
            'marca' => 'Fortinet',
            'modelo' => 'FG-60F',
            'numero_serie' => 'FGT60FTK200001',
            'criticidad' => 'alta',
            'ubicacion_id' => $this->ubicacion->id,
        ], $this->panolero);

        $this->assertEquals('disponible', $equipo->estado);

        // Docente reporta falla
        $descripcionFalla = 'El puerto WAN1 no responde a negociación link y el led de alimentación parpadea en ámbar.';
        $falla = $this->fallaService->reportarFalla($equipo, $this->docente, $descripcionFalla);

        $this->assertInstanceOf(Falla::class, $falla);
        $this->assertDatabaseHas('fallas', [
            'id' => $falla->id,
            'equipo_id' => $equipo->id,
            'reportada_por' => $this->docente->id,
            'estado' => 'reportada',
        ]);

        $this->assertNotNull($falla->fecha_reporte);
        $this->assertEquals($descripcionFalla, $falla->descripcion);

        // El equipo DEBE cambiar de estado preventivo a 'en_mantenimiento' para bloquearlo de nuevos préstamos
        $equipo->refresh();
        $this->assertEquals('en_mantenimiento', $equipo->estado);
    }

    /**
     * CP-06: Encargado de pañol revisa técnicamente la falla y restituye la disponibilidad del equipo tras resolverla.
     */
    public function test_panolero_atiende_falla_y_restituye_operatividad_al_resolver(): void
    {
        $equipo = $this->equipoService->registrarEquipo([
            'codigo_barras' => 'EQ-FALLA-002',
            'nombre' => 'Punto de Acceso Inalámbrico Cisco Aironet 2800',
            'marca' => 'Cisco Systems',
            'modelo' => 'AIR-AP2802I-A-K9',
            'numero_serie' => 'FOC99112233',
            'criticidad' => 'media',
            'ubicacion_id' => $this->ubicacion->id,
        ], $this->panolero);

        $falla = $this->fallaService->reportarFalla($equipo, $this->docente, 'No irradia SSID en banda 5GHz');

        // Pañolero pasa la falla a revisión
        $this->fallaService->atenderFalla($falla, $this->panolero, 'en_revision', 'Iniciando diagnóstico con consola serie');
        $falla->refresh();
        $this->assertEquals('en_revision', $falla->estado);
        $this->assertEquals($this->panolero->id, $falla->atendida_por);

        // Pañolero resuelve la falla tras reinstalar firmware
        $this->fallaService->atenderFalla($falla, $this->panolero, 'resuelta', 'Actualización de firmware limpia; pruebas de transmisión exitosas.');

        $falla->refresh();
        $this->assertEquals('resuelta', $falla->estado);

        // El equipo vuelve a estar disponible
        $equipo->refresh();
        $this->assertEquals('disponible', $equipo->estado);
    }

    /**
     * CP-06: Cuando la falla es irreparable o muy costosa, se deriva a propuesta de baja.
     */
    public function test_derivacion_a_baja_deja_equipo_pendiente_resolucion(): void
    {
        $equipo = $this->equipoService->registrarEquipo([
            'codigo_barras' => 'EQ-FALLA-003',
            'nombre' => 'Generador de Funciones Arbitrarias Tektronix AFG1022',
            'marca' => 'Tektronix',
            'modelo' => 'AFG1022',
            'numero_serie' => 'TEK44556677',
            'criticidad' => 'alta',
            'ubicacion_id' => $this->ubicacion->id,
        ], $this->panolero);

        $falla = $this->fallaService->reportarFalla($equipo, $this->docente, 'Cortocircuito en placa madre con olor a quemado');

        $this->fallaService->atenderFalla($falla, $this->panolero, 'derivada_a_baja', 'Placa base fundida; costo de repuesto supera el 90% del valor de un equipo nuevo.');

        $falla->refresh();
        $this->assertEquals('derivada_a_baja', $falla->estado);

        $equipo->refresh();
        $this->assertEquals('pendiente_resolucion', $equipo->estado);
    }
}
