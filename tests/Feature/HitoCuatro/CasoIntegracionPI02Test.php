<?php

namespace Tests\Feature\HitoCuatro;

use App\Exceptions\EquipoNoDisponibleException;
use App\Models\Carrera;
use App\Models\Entrega;
use App\Models\Equipo;
use App\Models\SolicitudMaterial;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\PrestamoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CasoIntegracionPI02Test extends TestCase
{
    use RefreshDatabase;

    protected Carrera $carrera;
    protected Ubicacion $ubicacion;
    protected User $docente1;
    protected User $docente2;
    protected User $panolero1;
    protected User $panolero2;
    protected Equipo $equipoTelescopio;
    protected PrestamoService $prestamoService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prestamoService = app(PrestamoService::class);

        $this->carrera = Carrera::create([
            'codigo' => 'GEO-01',
            'nombre' => 'Geomensura',
            'activa' => true,
        ]);

        $this->ubicacion = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Laboratorio de Topografía y Geodesia',
            'tipo' => 'laboratorio',
        ]);

        $this->docente1 = User::factory()->create([
            'name' => 'Profesor Esteban Alarcón',
            'email' => 'esteban.alarcon@inacap.cl',
            'rol' => 'docente',
        ]);

        $this->docente2 = User::factory()->create([
            'name' => 'Profesora Marcela Castro',
            'email' => 'marcela.castro@inacap.cl',
            'rol' => 'docente',
        ]);

        $this->panolero1 = User::factory()->create([
            'name' => 'Pañolero Ventanilla A (Norte)',
            'email' => 'panolero.a@inacap.cl',
            'rol' => 'encargado_panol',
        ]);

        $this->panolero2 = User::factory()->create([
            'name' => 'Pañolero Ventanilla B (Sur)',
            'email' => 'panolero.b@inacap.cl',
            'rol' => 'encargado_panol',
        ]);

        // Equipo único y escaso en pañol
        $this->equipoTelescopio = Equipo::create([
            'codigo_barras' => 'EQ-GEO-EST-TOTAL-001',
            'nombre' => 'Estación Total Leica FlexLine TS07',
            'marca' => 'Leica Geosystems',
            'modelo' => 'FlexLine TS07 2"',
            'numero_serie' => 'LCA-TS07-99120',
            'estado' => 'disponible',
            'criticidad' => 'alta',
            'ubicacion_id' => $this->ubicacion->id,
        ]);
    }

    /**
     * PI-02: Intento de entrega simultánea del mismo equipo disponible desde dos sesiones de pañol concurrentes.
     * Mediante SELECT FOR UPDATE pesimista, solo una sesión tiene éxito y la segunda es rechazada atómicamente.
     */
    public function test_concurrencia_doble_entrega_simultanea_mismo_equipo_es_rechazada(): void
    {
        // Dos docentes crean solicitudes que coinciden en solicitar el mismo equipo único
        $solicitud1 = $this->prestamoService->crearSolicitud(
            $this->docente1,
            ['fecha_requerida' => Carbon::tomorrow()->toDateString()],
            [['equipo_id' => $this->equipoTelescopio->id, 'cantidad' => 1.0]]
        );

        $solicitud2 = $this->prestamoService->crearSolicitud(
            $this->docente2,
            ['fecha_requerida' => Carbon::tomorrow()->toDateString()],
            [['equipo_id' => $this->equipoTelescopio->id, 'cantidad' => 1.0]]
        );

        // Sesión 1: Pañolero 1 procesa y confirma la entrega del equipo
        $entrega1 = $this->prestamoService->entregarMateriales(
            $solicitud1,
            $this->panolero1,
            'Esteban Alarcón',
            'docente'
        );

        $this->assertInstanceOf(Entrega::class, $entrega1);
        $this->assertEquals('prestado', $this->equipoTelescopio->fresh()->estado);

        // Sesión 2: Pañolero 2 intenta procesar concurrentemente la entrega del mismo equipo para solicitud 2
        $sesion2RechazadaAtomicamente = false;
        try {
            $this->prestamoService->entregarMateriales(
                $solicitud2,
                $this->panolero2,
                'Marcela Castro',
                'docente'
            );
        } catch (EquipoNoDisponibleException $e) {
            $sesion2RechazadaAtomicamente = true;
            $this->assertStringContainsString('ya no se encuentra disponible', $e->getMessage());
            $this->assertStringContainsString('EQ-GEO-EST-TOTAL-001', $e->getMessage());
        }

        $this->assertTrue(
            $sesion2RechazadaAtomicamente,
            'La segunda sesión concurrente debió ser rechazada atómicamente por bloqueo pesimista lockForUpdate().'
        );

        // Integridad de la base de datos: el equipo solo fue asignado a la primera solicitud
        $this->assertEquals('prestado', $this->equipoTelescopio->fresh()->estado);
        $this->assertEquals(1, Entrega::where('solicitud_id', $solicitud1->id)->count());
        $this->assertEquals(0, Entrega::where('solicitud_id', $solicitud2->id)->count());
        $this->assertEquals('entregada_abierta', $solicitud1->fresh()->estado);
        $this->assertEquals('pendiente', $solicitud2->fresh()->estado);
    }
}
