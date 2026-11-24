<?php

namespace Tests\Feature\Dashboard;

use App\Models\Carrera;
use App\Models\Devolucion;
use App\Models\Entrega;
use App\Models\Equipo;
use App\Models\Falla;
use App\Models\SolicitudMaterial;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\DashboardMetricasService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMetricasDashboardTest extends TestCase
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

        $this->carrera = Carrera::create([
            'codigo' => 'INF-01',
            'nombre' => 'Ingeniería en Informática',
            'activa' => true,
        ]);

        $this->ubicacion = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Laboratorio Redes',
            'tipo' => 'laboratorio',
        ]);

        $this->admin = User::factory()->create([
            'email' => 'admin.metricas@inacap.cl',
            'rol' => 'administrador',
            'activo' => true,
        ]);

        $this->panolero = User::factory()->create([
            'email' => 'panolero.metricas@inacap.cl',
            'rol' => 'encargado_panol',
            'activo' => true,
        ]);

        $this->docente = User::factory()->create([
            'email' => 'docente.metricas@inacap.cl',
            'rol' => 'docente',
            'activo' => true,
        ]);
    }

    public function test_administrador_accede_a_su_dashboard_con_metricas_analiticas(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Panel de Métricas y Trazabilidad Operativa');
        $response->assertSee('Puntualidad Estudiantes');
        $response->assertSee('¿En qué horas se piden más cosas?');
        $response->assertSee('Equipos que Más Fallan');
        $response->assertSee('Materiales Más Solicitados');
    }

    public function test_calculo_tasa_puntualidad_estudiantes_es_exacto(): void
    {
        $solicitud1 = SolicitudMaterial::create([
            'solicitante_id' => $this->docente->id,
            'fecha_solicitud' => now(),
            'fecha_requerida' => now()->toDateString(),
            'estado' => 'cerrada',
        ]);

        $solicitud2 = SolicitudMaterial::create([
            'solicitante_id' => $this->docente->id,
            'fecha_solicitud' => now(),
            'fecha_requerida' => now()->toDateString(),
            'estado' => 'cerrada',
        ]);

        // Entrega 1: Devolución a tiempo
        $entrega1 = Entrega::create([
            'solicitud_id' => $solicitud1->id,
            'entregado_por' => $this->panolero->id,
            'receptor_nombre' => 'Juan Alumno',
            'receptor_tipo' => 'estudiante',
            'receptor_credencial_rut' => '20.111.222-3',
            'credencial_verificada' => true,
            'fecha_entrega' => Carbon::parse('2026-11-20 10:00:00'),
            'hora_entrega' => '10:00:00',
            'hora_devolucion_acordada' => '12:00:00',
        ]);

        Devolucion::create([
            'entrega_id' => $entrega1->id,
            'recibido_por' => $this->panolero->id,
            'fecha_devolucion' => Carbon::parse('2026-11-20 11:45:00'),
            'conforme' => true,
        ]);

        // Entrega 2: Devolución con retraso
        $entrega2 = Entrega::create([
            'solicitud_id' => $solicitud2->id,
            'entregado_por' => $this->panolero->id,
            'receptor_nombre' => 'Maria Alumna',
            'receptor_tipo' => 'estudiante',
            'receptor_credencial_rut' => '21.333.444-5',
            'credencial_verificada' => true,
            'fecha_entrega' => Carbon::parse('2026-11-20 10:00:00'),
            'hora_entrega' => '10:00:00',
            'hora_devolucion_acordada' => '12:00:00',
        ]);

        Devolucion::create([
            'entrega_id' => $entrega2->id,
            'recibido_por' => $this->panolero->id,
            'fecha_devolucion' => Carbon::parse('2026-11-20 12:30:00'),
            'conforme' => true,
        ]);

        $service = app(DashboardMetricasService::class);
        $metricas = $service->obtenerMetricasPuntualidadEstudiantes('historico');

        $this->assertEquals(2, $metricas['total_devoluciones_estudiantes']);
        $this->assertEquals(1, $metricas['a_tiempo']);
        $this->assertEquals(1, $metricas['con_retraso']);
        $this->assertEquals(50.0, $metricas['porcentaje_a_tiempo']);
    }

    public function test_distribucion_horaria_y_top_fallas_calculan_correctamente(): void
    {
        $equipo = Equipo::create([
            'codigo_barras' => 'EQ-FLK-001',
            'nombre' => 'Multímetro Digital',
            'marca' => 'Fluke',
            'modelo' => '115',
            'numero_serie' => 'SN-115-999',
            'estado' => 'disponible',
            'criticidad' => 'media',
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        Falla::create([
            'equipo_id' => $equipo->id,
            'reportada_por' => $this->docente->id,
            'descripcion' => 'Fusible quemado en medición de corriente',
            'fecha_reporte' => now(),
            'estado' => 'reportada',
        ]);

        Falla::create([
            'equipo_id' => $equipo->id,
            'reportada_por' => $this->docente->id,
            'descripcion' => 'Punta de prueba dañada',
            'fecha_reporte' => now(),
            'estado' => 'en_revision',
        ]);

        $solicitud = SolicitudMaterial::create([
            'solicitante_id' => $this->docente->id,
            'fecha_solicitud' => now(),
            'fecha_requerida' => now()->toDateString(),
            'estado' => 'cerrada',
        ]);

        Entrega::create([
            'solicitud_id' => $solicitud->id,
            'entregado_por' => $this->panolero->id,
            'receptor_nombre' => 'Pedro Soto',
            'receptor_tipo' => 'estudiante',
            'fecha_entrega' => Carbon::parse(now()->format('Y-m-d').' 10:15:00'),
            'hora_entrega' => '10:15:00',
            'hora_devolucion_acordada' => '12:00:00',
        ]);

        $service = app(DashboardMetricasService::class);
        $topFallas = $service->obtenerTopEquiposConFallas(5, '30_dias');
        $distribucion = $service->obtenerDistribucionHoraria('30_dias');

        $this->assertCount(1, $topFallas);
        $this->assertEquals(2, $topFallas->first()->total_fallas);
        $this->assertEquals('Multímetro Digital', $topFallas->first()->equipo->nombre);

        $hora10 = collect($distribucion['horas'])->firstWhere('hora_num', 10);
        $this->assertNotNull($hora10);
        $this->assertGreaterThanOrEqual(1, $hora10['total']);
    }

    public function test_encargado_panol_y_docente_no_ven_el_panel_analitico_de_administrador(): void
    {
        // Encargado de pañol
        $resPanolero = $this->actingAs($this->panolero)->get(route('dashboard'));
        $resPanolero->assertStatus(200);
        $resPanolero->assertDontSee('Panel de Métricas y Trazabilidad Operativa');
        $resPanolero->assertSee('Despacho y Devoluciones');

        // Docente
        $resDocente = $this->actingAs($this->docente)->get(route('dashboard'));
        $resDocente->assertStatus(200);
        $resDocente->assertDontSee('Panel de Métricas y Trazabilidad Operativa');
        $resDocente->assertSee('Solicitud de Materiales');
    }
}
