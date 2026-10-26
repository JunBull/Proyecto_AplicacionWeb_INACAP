<?php

namespace Tests\Feature\HitoDos;

use App\Models\Carrera;
use App\Models\Equipo;
use App\Models\Mantenimiento;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\EquipoService;
use App\Services\MantenimientoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CasoPruebaCP05Test extends TestCase
{
    use RefreshDatabase;

    protected Carrera $carrera;
    protected Ubicacion $ubicacion;
    protected User $panolero;
    protected EquipoService $equipoService;
    protected MantenimientoService $mantenimientoService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->carrera = Carrera::create([
            'codigo' => 'INF-03',
            'nombre' => 'Ingeniería en Telecomunicaciones',
            'activa' => true,
        ]);

        $this->ubicacion = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Laboratorio de Redes Industriales',
            'tipo' => 'laboratorio',
            'padre_id' => null,
        ]);

        $this->panolero = User::factory()->create([
            'email' => 'panolero.cp05@inacap.cl',
            'rol' => 'encargado_panol',
        ]);

        $this->equipoService = app(EquipoService::class);
        $this->mantenimientoService = app(MantenimientoService::class);
    }

    /**
     * CP-05: Programar mantenimientos semestrales y anuales en el calendario técnico.
     */
    public function test_programar_mantenimiento_preventivo_semestral_y_anual(): void
    {
        $equipo = $this->equipoService->registrarEquipo([
            'codigo_barras' => 'EQ-MANT-001',
            'nombre' => 'Router Cisco Integrated Services Router 4331',
            'marca' => 'Cisco Systems',
            'modelo' => 'ISR 4331',
            'numero_serie' => 'FOC998877AA',
            'criticidad' => 'alta',
            'ubicacion_id' => $this->ubicacion->id,
        ], $this->panolero);

        $fechaSemestral = now()->addMonths(6)->toDateString();
        $mantSemestral = $this->mantenimientoService->programarMantenimiento(
            $equipo,
            'preventivo',
            'semestral',
            $fechaSemestral,
            null,
            'Limpieza de filtros y verificación de puertos'
        );

        $this->assertEquals('preventivo', $mantSemestral->tipo);
        $this->assertEquals('semestral', $mantSemestral->frecuencia);
        $this->assertEquals($fechaSemestral, $mantSemestral->fecha_programada->toDateString());
        $this->assertEquals('programado', $mantSemestral->estado);

        $fechaAnual = now()->addYear()->toDateString();
        $mantAnual = $this->mantenimientoService->programarMantenimiento(
            $equipo,
            'preventivo',
            'anual',
            $fechaAnual,
            null,
            'Mantenimiento integral y calibración anual'
        );

        $this->assertEquals('anual', $mantAnual->frecuencia);
        $this->assertDatabaseCount('mantenimientos', 2);
    }

    /**
     * CP-05: El sistema genera alertas preventivas en las fechas configuradas según la criticidad (Alta: 30d, Media: 15d, Baja: 7d).
     */
    public function test_generacion_alertas_segun_nivel_criticidad_alta_media_baja(): void
    {
        // Equipo 1: Criticidad ALTA (umbral 30 días de anticipación)
        $eqAlta = $this->equipoService->registrarEquipo([
            'codigo_barras' => 'EQ-CRIT-ALTA',
            'nombre' => 'Servidor Blade Dell PowerEdge M640 - Core Académico',
            'marca' => 'Dell',
            'modelo' => 'M640',
            'numero_serie' => 'SN-ALTA-001',
            'criticidad' => 'alta',
            'ubicacion_id' => $this->ubicacion->id,
        ], $this->panolero);

        // Equipo 2: Criticidad BAJA (umbral 7 días de anticipación)
        $eqBaja = $this->equipoService->registrarEquipo([
            'codigo_barras' => 'EQ-CRIT-BAJA',
            'nombre' => 'Monitor LCD 24 Pulgadas IPS Full HD - Puesto Trabajo',
            'marca' => 'LG',
            'modelo' => '24MP400',
            'numero_serie' => 'SN-BAJA-002',
            'criticidad' => 'baja',
            'ubicacion_id' => $this->ubicacion->id,
        ], $this->panolero);

        // Programar ambos mantenimientos para dentro de 20 días
        $fechaObjetivo = now()->addDays(20);

        $this->mantenimientoService->programarMantenimiento($eqAlta, 'preventivo', 'semestral', $fechaObjetivo);
        $this->mantenimientoService->programarMantenimiento($eqBaja, 'preventivo', 'semestral', $fechaObjetivo);

        // A 20 días de la fecha objetivo:
        // - El equipo de criticidad ALTA (umbral 30d) DEBE gatillar alerta (20 <= 30)
        // - El equipo de criticidad BAJA (umbral 7d) NO debe gatillar alerta todavía (20 > 7)
        $alertas = $this->mantenimientoService->evaluarAlertasMantenimiento(now());

        $this->assertCount(1, $alertas);
        $this->assertEquals($eqAlta->id, $alertas[0]['equipo_id']);
        $this->assertEquals('preventiva', $alertas[0]['tipo_alerta']);
        $this->assertEquals('alta', $alertas[0]['criticidad']);

        // Si evaluamos cuando faltan 5 días para la fecha objetivo:
        // Ambos deben generar alerta preventiva
        $alertasCercanas = $this->mantenimientoService->evaluarAlertasMantenimiento(now()->addDays(15));
        $this->assertCount(2, $alertasCercanas);
    }

    /**
     * CP-05: El comando de Artisan del Scheduler detecta y procesa alertas y vencimientos.
     */
    public function test_comando_artisan_evaluar_mantenimientos_y_marcado_vencido(): void
    {
        $equipo = $this->equipoService->registrarEquipo([
            'codigo_barras' => 'EQ-VENC-001',
            'nombre' => 'Switch Capa de Distribución Cisco Catalyst 3850',
            'marca' => 'Cisco Systems',
            'modelo' => 'WS-C3850-24T',
            'numero_serie' => 'SN-VENC-999',
            'criticidad' => 'alta',
            'ubicacion_id' => $this->ubicacion->id,
        ], $this->panolero);

        // Mantenimiento cuya fecha programada fue hace 3 días
        $mantVencido = $this->mantenimientoService->programarMantenimiento(
            $equipo,
            'preventivo',
            'semestral',
            now()->subDays(3)
        );

        $this->artisan('panol:evaluar-mantenimientos')
            ->assertSuccessful();

        $mantVencido->refresh();
        $this->assertEquals('vencido', $mantVencido->estado);
    }
}
