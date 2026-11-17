<?php

namespace Tests\Feature\Panol;

use App\Models\Carrera;
use App\Models\Equipo;
use App\Models\Mantenimiento;
use App\Models\Ubicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class MantenimientoSubtiposInstructivoTest extends TestCase
{
    use RefreshDatabase;

    private User $panolero;

    private Carrera $carrera;

    private Ubicacion $ubicacion;

    private Equipo $equipoEstandar;

    private Equipo $equipoCalibrable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->panolero = User::factory()->create([
            'rol' => 'encargado_panol',
            'activo' => true,
        ]);

        $this->carrera = Carrera::create([
            'codigo' => 'AUT-IND',
            'nombre' => 'Automatización y Control Industrial',
            'activa' => true,
        ]);

        $this->ubicacion = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Laboratorio de Instrumentación L2',
            'codigo' => 'L2-INST',
            'tipo' => 'laboratorio',
        ]);

        $this->equipoEstandar = Equipo::create([
            'nombre' => 'Entrenador de Máquinas Eléctricas',
            'marca' => 'Lucas-Nülle',
            'modelo' => 'LN-5000',
            'numero_serie' => 'SN-LN-5001',
            'codigo_barras' => 'EQ-AUT-LN-01',
            'ubicacion_id' => $this->ubicacion->id,
            'estado' => 'disponible',
            'criticidad' => 'alta',
            'requiere_calibracion' => false,
        ]);

        $this->equipoCalibrable = Equipo::create([
            'nombre' => 'Wattmetro Digital Trifásico Fluke',
            'marca' => 'Fluke',
            'modelo' => 'Fluke 435 II',
            'numero_serie' => 'SN-FLK-435-09',
            'codigo_barras' => 'EQ-ELC-FLK-09',
            'ubicacion_id' => $this->ubicacion->id,
            'estado' => 'disponible',
            'criticidad' => 'alta',
            'requiere_calibracion' => true,
        ]);
    }

    public function test_subtipos_catalogo_contiene_codigos_oficiales_del_instructivo(): void
    {
        $codigosEsperados = ['REV-A', 'REV-B', 'REV-C', 'PFG', 'MC-PLAN', 'MC-URG'];

        foreach ($codigosEsperados as $codigo) {
            $this->assertArrayHasKey($codigo, Mantenimiento::SUBTIPOS);
            $this->assertNotEmpty(Mantenimiento::SUBTIPOS[$codigo]['nombre']);
            $this->assertNotEmpty(Mantenimiento::SUBTIPOS[$codigo]['descripcion']);
        }
    }

    public function test_puede_programar_mantenimiento_preventivo_con_subtipo_rev_c_calibracion(): void
    {
        $this->actingAs($this->panolero);

        Volt::test('pages.mantenimientos.index')
            ->call('abrirModalProgramar')
            ->assertSet('mostrarModalProgramar', true)
            ->set('equipo_id', $this->equipoCalibrable->id)
            ->set('subtipo', 'REV-C')
            ->set('frecuencia', 'semestral')
            ->set('fecha_programada', now()->addMonths(6)->toDateString())
            ->set('observaciones', 'Calibración oficial según Instructivo de Equipamiento v2.0.')
            ->call('guardarMantenimiento')
            ->assertHasNoErrors()
            ->assertSet('mostrarModalProgramar', false)
            ->assertSee('Mantenimiento preventivo programado con éxito');

        $this->assertDatabaseHas('mantenimientos', [
            'equipo_id' => $this->equipoCalibrable->id,
            'tipo' => 'preventivo',
            'subtipo' => 'REV-C',
            'frecuencia' => 'semestral',
            'estado' => 'programado',
        ]);
    }

    public function test_equipo_calibrable_muestra_alerta_sugerida_en_modal_y_sugiere_rev_c(): void
    {
        $this->actingAs($this->panolero);

        Volt::test('pages.mantenimientos.index')
            ->set('equipo_id', $this->equipoCalibrable->id)
            ->call('abrirModalProgramar')
            ->assertSet('mostrarModalProgramar', true)
            ->assertSee('Este equipo requiere calibración y ajuste periódico según la matriz institucional')
            ->assertSet('subtipo', 'REV-C');
    }

    public function test_programar_mantenimiento_correctivo_asigna_frecuencia_no_aplica(): void
    {
        $this->actingAs($this->panolero);

        Volt::test('pages.mantenimientos.index')
            ->call('abrirModalProgramar')
            ->set('equipo_id', $this->equipoEstandar->id)
            ->set('subtipo', 'MC-PLAN')
            ->assertSet('tipo', 'correctivo')
            ->assertSet('frecuencia', 'no_aplica')
            ->set('fecha_programada', now()->addDays(3)->toDateString())
            ->set('observaciones', 'Reemplazo programado de relé de protección térmico.')
            ->call('guardarMantenimiento')
            ->assertHasNoErrors()
            ->assertSet('mostrarModalProgramar', false);

        $this->assertDatabaseHas('mantenimientos', [
            'equipo_id' => $this->equipoEstandar->id,
            'tipo' => 'correctivo',
            'subtipo' => 'MC-PLAN',
            'frecuencia' => 'no_aplica',
            'estado' => 'programado',
        ]);
    }

    public function test_tabla_activos_muestra_nombre_subtipo_y_texto_explicativo_de_burbuja(): void
    {
        $this->actingAs($this->panolero);

        Mantenimiento::create([
            'equipo_id' => $this->equipoCalibrable->id,
            'tipo' => 'preventivo',
            'subtipo' => 'REV-C',
            'frecuencia' => 'anual',
            'fecha_programada' => now()->addMonths(12)->toDateString(),
            'estado' => 'programado',
            'observaciones' => 'Inspección de precisión y tolerancia.',
        ]);

        Volt::test('pages.mantenimientos.index')
            ->assertSet('pestanaActiva', 'preventivo')
            ->assertSee('Calibración y Ajuste')
            ->assertSee('Inspección de precisión, sensibilidad y exactitud')
            ->assertDontSee('[REV-C]');
    }

    public function test_selector_de_subtipos_no_muestra_codigos_en_las_opciones(): void
    {
        $this->actingAs($this->panolero);

        Volt::test('pages.mantenimientos.index')
            ->call('abrirModalProgramar')
            ->assertSee('Revisión General')
            ->assertSee('Mecánica y Eléctrica')
            ->assertSee('Calibración y Ajuste')
            ->assertSee('Prueba Funcional')
            ->assertSee('Correctivo Programado')
            ->assertSee('Correctivo Emergencia')
            ->assertDontSee('[REV-A]')
            ->assertDontSee('[REV-B]')
            ->assertDontSee('[REV-C]')
            ->assertDontSee('[PFG]')
            ->assertDontSee('[MC-P]')
            ->assertDontSee('[MC-E]');
    }
}
