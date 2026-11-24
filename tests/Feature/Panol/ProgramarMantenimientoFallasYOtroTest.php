<?php

namespace Tests\Feature\Panol;

use App\Models\Carrera;
use App\Models\Equipo;
use App\Models\Falla;
use App\Models\Mantenimiento;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\MantenimientoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ProgramarMantenimientoFallasYOtroTest extends TestCase
{
    use RefreshDatabase;

    private User $panolero;

    private Carrera $carrera;

    private Ubicacion $ubicacion;

    private Equipo $equipoConFalla;

    private Equipo $equipoSano;

    private Falla $falla;

    protected function setUp(): void
    {
        parent::setUp();

        $this->panolero = User::factory()->create([
            'rol' => 'encargado_panol',
            'activo' => true,
        ]);

        $this->carrera = Carrera::create([
            'codigo' => 'TEL-01',
            'nombre' => 'Telecomunicaciones y Conectividad',
            'activa' => true,
        ]);

        $this->ubicacion = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Laboratorio de Redes y Microondas',
            'tipo' => 'laboratorio',
        ]);

        $this->equipoConFalla = Equipo::create([
            'codigo_barras' => 'EQ-TEK-2001',
            'nombre' => 'Osciloscopio Digital Tektronix 2 Canales',
            'marca' => 'Tektronix',
            'modelo' => 'TBS1052B',
            'numero_serie' => 'SN-TEK-001',
            'criticidad' => 'alta',
            'estado' => 'en_mantenimiento',
            'ubicacion_id' => $this->ubicacion->id,
            'requiere_calibracion' => true,
        ]);

        $this->equipoSano = Equipo::create([
            'codigo_barras' => 'EQ-FLUKE-179',
            'nombre' => 'Multímetro Digital True-RMS Fluke 179',
            'marca' => 'Fluke',
            'modelo' => '179',
            'numero_serie' => 'SN-FLK-002',
            'criticidad' => 'media',
            'estado' => 'disponible',
            'ubicacion_id' => $this->ubicacion->id,
            'requiere_calibracion' => true,
        ]);

        $this->falla = Falla::create([
            'equipo_id' => $this->equipoConFalla->id,
            'ubicacion_id' => $this->ubicacion->id,
            'reportada_por' => $this->panolero->id,
            'descripcion' => 'Canal 1 presenta ruido térmico excesivo y atenuación de 10 dB.',
            'fecha_reporte' => now(),
            'estado' => 'reportada',
        ]);
    }

    public function test_modal_muestra_equipos_con_falla_no_resuelta_y_opcion_otro(): void
    {
        $this->actingAs($this->panolero);

        Volt::test('pages.mantenimientos.index')
            ->call('abrirModalProgramar')
            ->assertSet('mostrarModalProgramar', true)
            ->assertSee('Equipos con Falla Reportada')
            ->assertSee("[{$this->equipoConFalla->codigo_barras}]")
            ->assertSee('Falla #INC-00001')
            ->assertSee('Otro equipo (Escanear o ingresar código de barras)');
    }

    public function test_seleccionar_equipo_con_falla_restringe_a_mantenimiento_correctivo(): void
    {
        $this->actingAs($this->panolero);

        Volt::test('pages.mantenimientos.index')
            ->call('abrirModalProgramar')
            ->set('selectorEquipo', "falla_{$this->falla->id}")
            ->assertSet('tipo', 'correctivo')
            ->assertSet('subtipo', 'MC-PLAN')
            ->assertSet('frecuencia', 'no_aplica')
            ->assertSet('falla_id', $this->falla->id)
            ->assertSet('equipo_id', $this->equipoConFalla->id)
            ->assertSee('solo permite intervenciones correctivas')
            ->set('fecha_programada', now()->addDays(5)->toDateString())
            ->set('observaciones', 'Reemplazo de condensadores en etapa de entrada del Canal 1.')
            ->call('guardarMantenimiento')
            ->assertHasNoErrors()
            ->assertSet('mostrarModalProgramar', false);

        $this->assertDatabaseHas('mantenimientos', [
            'equipo_id' => $this->equipoConFalla->id,
            'falla_id' => $this->falla->id,
            'tipo' => 'correctivo',
            'subtipo' => 'MC-PLAN',
            'frecuencia' => 'no_aplica',
            'estado' => 'programado',
        ]);
    }

    public function test_seleccionar_otro_equipo_permite_ingresar_codigo_y_programar_preventivo(): void
    {
        $this->actingAs($this->panolero);

        Volt::test('pages.mantenimientos.index')
            ->call('abrirModalProgramar')
            ->set('selectorEquipo', 'otro')
            ->assertSet('tipo', 'preventivo')
            ->assertSet('subtipo', 'REV-A')
            ->assertSet('frecuencia', 'semestral')
            // Ingresar el código del multímetro sano
            ->set('codigo_equipo_otro', $this->equipoSano->codigo_barras)
            ->assertSet('equipo_id', $this->equipoSano->id)
            ->assertSee($this->equipoSano->nombre)
            ->set('subtipo', 'REV-C')
            ->set('frecuencia', 'anual')
            ->set('fecha_programada', now()->addYear()->toDateString())
            ->set('observaciones', 'Calibración anual con patrón de referencia Fluke 5520A.')
            ->call('guardarMantenimiento')
            ->assertHasNoErrors()
            ->assertSet('mostrarModalProgramar', false);

        $this->assertDatabaseHas('mantenimientos', [
            'equipo_id' => $this->equipoSano->id,
            'falla_id' => null,
            'tipo' => 'preventivo',
            'subtipo' => 'REV-C',
            'frecuencia' => 'anual',
            'estado' => 'programado',
        ]);
    }

    public function test_ingresar_codigo_inexistente_en_otro_arroja_error(): void
    {
        $this->actingAs($this->panolero);

        Volt::test('pages.mantenimientos.index')
            ->call('abrirModalProgramar')
            ->set('selectorEquipo', 'otro')
            ->set('codigo_equipo_otro', 'CODIGO-TOTALMENTE-INEXISTENTE')
            ->assertSet('equipo_id', null)
            ->call('guardarMantenimiento')
            ->assertHasErrors(['codigo_equipo_otro'])
            ->assertSet('mostrarModalProgramar', true);
    }

    public function test_iniciar_y_finalizar_mantenimiento_correctivo_con_veredicto_conforme_cierra_falla_vinculada(): void
    {
        $this->actingAs($this->panolero);
        $service = app(MantenimientoService::class);

        // Programar correctivo con falla_id
        $mant = $service->programarMantenimiento(
            $this->equipoConFalla,
            'correctivo',
            'no_aplica',
            now()->toDateString(),
            null,
            'Reparación de atenuador de entrada',
            'MC-PLAN',
            $this->falla->id
        );

        $this->assertEquals('reportada', $this->falla->fresh()->estado);

        // Iniciar mantenimiento
        $service->iniciarMantenimiento($mant);
        $this->assertEquals('en_proceso', $mant->fresh()->estado);
        $this->assertEquals('en_reparacion', $this->falla->fresh()->estado);

        // Finalizar con veredicto conforme
        $service->registrarEjecucion(
            $mant,
            now()->toDateString(),
            'Se sustituyó etapa amplificadora y calibró respuesta en frecuencia.',
            null,
            $this->panolero,
            'conforme'
        );

        $this->assertEquals('realizado', $mant->fresh()->estado);
        $this->assertEquals('disponible', $this->equipoConFalla->fresh()->estado);
        $this->assertEquals('resuelta', $this->falla->fresh()->estado);
        $this->assertEquals($this->panolero->id, $this->falla->fresh()->atendida_por);
        $this->assertStringContainsString('Resolución por Mantenimiento', $this->falla->fresh()->descripcion);
    }
}
