<?php

namespace Tests\Feature\Panol;

use App\Models\Carrera;
use App\Models\Equipo;
use App\Models\Falla;
use App\Models\Mantenimiento;
use App\Models\Ubicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PanoleroMantenimientosFlujoTest extends TestCase
{
    use RefreshDatabase;

    private User $panolero;

    private Carrera $carrera;

    private Ubicacion $ubicacion;

    private Equipo $equipo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->panolero = User::factory()->create([
            'rol' => 'encargado_panol',
            'activo' => true,
        ]);

        $this->carrera = Carrera::create([
            'codigo' => 'INF-ING',
            'nombre' => 'Ingeniería en Informática',
            'activa' => true,
        ]);

        $this->ubicacion = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Laboratorio L1 - Redes',
            'codigo' => 'L1-RED',
            'tipo' => 'laboratorio',
        ]);

        $this->equipo = Equipo::create([
            'nombre' => 'Notebook Lenovo ThinkPad T14',
            'marca' => 'Lenovo',
            'modelo' => 'ThinkPad T14 Gen 2',
            'numero_serie' => 'SN-LNV-999',
            'codigo_barras' => 'EQ-LNV-T14-99',
            'ubicacion_id' => $this->ubicacion->id,
            'estado' => 'disponible',
            'criticidad' => 'alta',
        ]);
    }

    public function test_panolero_puede_conmutar_entre_pestanas(): void
    {
        $this->actingAs($this->panolero);

        Volt::test('pages.mantenimientos.index')
            ->assertSee('Mantenimientos')
            ->assertSee('Mantenimiento Preventivo')
            ->assertSee('Equipos reportados con fallas')
            ->assertSet('pestanaActiva', 'preventivo')
            ->assertSet('subfiltroPreventivo', 'activos')
            ->call('cambiarSubfiltroPreventivo', 'completados')
            ->assertSet('subfiltroPreventivo', 'completados')
            ->call('cambiarPestana', 'fallas')
            ->assertSet('pestanaActiva', 'fallas')
            ->assertSet('subfiltroFallas', 'pendientes')
            ->assertSee('Folio / Recurso')
            ->call('cambiarSubfiltroFallas', 'resueltas')
            ->assertSet('subfiltroFallas', 'resueltas')
            ->call('cambiarPestana', 'historial')
            ->assertSet('pestanaActiva', 'preventivo')
            ->assertSet('subfiltroPreventivo', 'completados');
    }

    public function test_fechas_se_formatean_en_dd_mm_yy(): void
    {
        $this->actingAs($this->panolero);

        Mantenimiento::create([
            'equipo_id' => $this->equipo->id,
            'tipo' => 'preventivo',
            'frecuencia' => 'semestral',
            'fecha_programada' => '2027-04-29',
            'estado' => 'programado',
            'observaciones' => 'Revisión semestral',
        ]);

        Volt::test('pages.mantenimientos.index')
            ->assertSet('pestanaActiva', 'preventivo')
            ->assertSee('29-04-27');
    }

    public function test_solicitar_baja_in_situ_desde_historial(): void
    {
        $this->actingAs($this->panolero);

        $mantenimiento = Mantenimiento::create([
            'equipo_id' => $this->equipo->id,
            'tipo' => 'correctivo',
            'frecuencia' => 'no_aplica',
            'fecha_programada' => '2026-10-28',
            'fecha_realizada' => '2026-10-29',
            'estado' => 'realizado',
            'observaciones' => 'Falla reiterada en placa madre',
        ]);

        Volt::test('pages.mantenimientos.index')
            ->call('cambiarPestana', 'historial')
            ->assertSee('Solicitar Baja')
            ->call('abrirModalSolicitarBaja', $this->equipo->id)
            ->assertSet('mostrarModalBaja', true)
            ->assertSet('bajaEquipoId', $this->equipo->id)
            ->set('bajaTipo', 'baja')
            ->set('bajaMotivo', 'Equipo con daño irreversible en placa madre cuyo costo de reparación supera el 70% del valor de reposición.')
            ->call('confirmarSolicitudBaja')
            ->assertHasNoErrors()
            ->assertSet('mostrarModalBaja', false)
            ->assertSee('Propuesta de baja enviada exitosamente');

        $this->assertDatabaseHas('bajas_reemplazos', [
            'equipo_id' => $this->equipo->id,
            'tipo' => 'baja',
            'estado' => 'propuesta',
        ]);

        $this->assertEquals('pendiente_resolucion', $this->equipo->fresh()->estado);
    }

    public function test_recepcionar_falla_reportada_inicia_diagnostico_directamente(): void
    {
        $this->actingAs($this->panolero);

        $docente = User::factory()->create([
            'rol' => 'docente',
            'activo' => true,
        ]);

        $falla = Falla::create([
            'equipo_id' => $this->equipo->id,
            'ubicacion_id' => $this->ubicacion->id,
            'reportada_por' => $docente->id,
            'descripcion' => 'Pantalla azul recurrente al encender en el laboratorio.',
            'fecha_reporte' => now(),
            'estado' => 'reportada',
        ]);

        Volt::test('pages.mantenimientos.index')
            ->call('cambiarPestana', 'fallas')
            ->assertSee('#INC-00001')
            ->assertSee('Recepcionar en Pañol')
            ->call('recepcionarFalla', $falla->id)
            ->assertHasNoErrors()
            ->assertSee('recepcionada en pañol');

        $this->assertEquals('en_revision', $falla->fresh()->estado);
        $this->assertEquals($this->panolero->id, $falla->fresh()->atendida_por);
    }

    public function test_resolver_incidencia_tecnica_reintegra_a_disponible(): void
    {
        $this->actingAs($this->panolero);

        $falla = Falla::create([
            'equipo_id' => $this->equipo->id,
            'ubicacion_id' => $this->ubicacion->id,
            'reportada_por' => $this->panolero->id,
            'atendida_por' => $this->panolero->id,
            'descripcion' => 'Equipo en taller por fallas de ventilación.',
            'fecha_reporte' => now(),
            'estado' => 'en_revision',
        ]);

        $this->equipo->update(['estado' => 'en_mantenimiento']);

        Volt::test('pages.mantenimientos.index')
            ->call('cambiarPestana', 'fallas')
            ->assertSee('Resolver Incidencia')
            ->call('abrirModalAtenderFalla', $falla->id)
            ->assertSet('mostrarModalAtender', true)
            ->assertSet('fallaNuevoEstado', 'resuelta')
            ->set('fallaObservaciones', 'Se limpiaron ventiladores y se sustituyó pasta térmica. Temperatura óptima.')
            ->call('confirmarAtencionFalla')
            ->assertHasNoErrors()
            ->assertSet('mostrarModalAtender', false)
            ->assertSee('Incidencia resuelta exitosamente');

        $this->assertEquals('resuelta', $falla->fresh()->estado);
        $this->assertEquals('disponible', $this->equipo->fresh()->estado);
    }

    public function test_resolver_incidencia_con_derivacion_a_baja_genera_propuesta(): void
    {
        $this->actingAs($this->panolero);

        $falla = Falla::create([
            'equipo_id' => $this->equipo->id,
            'ubicacion_id' => $this->ubicacion->id,
            'reportada_por' => $this->panolero->id,
            'atendida_por' => $this->panolero->id,
            'descripcion' => 'Cortocircuito en conector principal.',
            'fecha_reporte' => now(),
            'estado' => 'en_revision',
        ]);

        $this->equipo->update(['estado' => 'en_mantenimiento']);

        Volt::test('pages.mantenimientos.index')
            ->call('cambiarPestana', 'fallas')
            ->call('abrirModalAtenderFalla', $falla->id)
            ->set('fallaNuevoEstado', 'derivada_a_baja')
            ->set('fallaObservaciones', 'Daño severo e irreparable en chipset y placa principal.')
            ->call('confirmarAtencionFalla')
            ->assertHasNoErrors()
            ->assertSet('mostrarModalAtender', false)
            ->assertSee('Incidencia derivada a propuesta de baja');

        $this->assertEquals('derivada_a_baja', $falla->fresh()->estado);
        $this->assertEquals('pendiente_resolucion', $this->equipo->fresh()->estado);

        $this->assertDatabaseHas('bajas_reemplazos', [
            'equipo_id' => $this->equipo->id,
            'falla_id' => $falla->id,
            'tipo' => 'baja',
            'estado' => 'propuesta',
        ]);
    }

    public function test_fallas_resueltas_en_subfiltro_historial_no_muestran_botones_de_gestion(): void
    {
        $this->actingAs($this->panolero);

        $falla = Falla::create([
            'equipo_id' => $this->equipo->id,
            'ubicacion_id' => $this->ubicacion->id,
            'reportada_por' => $this->panolero->id,
            'atendida_por' => $this->panolero->id,
            'descripcion' => 'Problema en sistema operativo.',
            'fecha_reporte' => now(),
            'estado' => 'resuelta',
        ]);

        Volt::test('pages.mantenimientos.index')
            ->call('cambiarPestana', 'fallas')
            ->call('cambiarSubfiltroFallas', 'resueltas')
            ->assertSee('Resuelta')
            ->assertDontSee('Recepcionar en Pañol')
            ->assertDontSee('Resolver Incidencia')
            ->assertDontSee('Gestionar Falla');
    }

    public function test_menu_activos_muestra_titulos_simplificados(): void
    {
        $this->actingAs($this->panolero);

        $response = $this->get(route('panol.mantenimientos'));
        $response->assertStatus(200);
        $response->assertSee('Equipos');
        $response->assertSee('Mantenimientos');
        $response->assertSee('Bajas');
        $response->assertDontSee('Catálogo físico e individualizado');
        $response->assertDontSee('Preventivos y reportes docentes');
        $response->assertDontSee('Resolución y desechos de activos');
    }

    public function test_programar_mantenimiento_preventivo_exhibe_codigo_institucional(): void
    {
        $this->actingAs($this->panolero);

        Volt::test('pages.mantenimientos.index')
            ->call('abrirModalProgramar')
            ->assertSet('mostrarModalProgramar', true)
            ->assertSee("[{$this->equipo->codigo_barras}] {$this->equipo->nombre}")
            ->set('equipo_id', $this->equipo->id)
            ->set('frecuencia', 'semestral')
            ->set('fecha_programada', now()->addMonths(6)->toDateString())
            ->set('observaciones', 'Calibración periódica semestral')
            ->call('guardarMantenimiento')
            ->assertHasNoErrors()
            ->assertSet('mostrarModalProgramar', false)
            ->assertSee('Mantenimiento preventivo programado con éxito');

        $this->assertDatabaseHas('mantenimientos', [
            'equipo_id' => $this->equipo->id,
            'tipo' => 'preventivo',
            'frecuencia' => 'semestral',
            'estado' => 'programado',
        ]);
    }

    public function test_iniciar_mantenimiento_pasa_a_en_proceso_y_bloquea_equipo_en_mantenimiento(): void
    {
        $this->actingAs($this->panolero);

        $mantenimiento = Mantenimiento::create([
            'equipo_id' => $this->equipo->id,
            'tipo' => 'preventivo',
            'frecuencia' => 'semestral',
            'fecha_programada' => now()->toDateString(),
            'estado' => 'programado',
        ]);

        $this->assertEquals('disponible', $this->equipo->fresh()->estado);

        Volt::test('pages.mantenimientos.index')
            ->assertSee('Iniciar Mantenimiento')
            ->call('iniciarMantenimiento', $mantenimiento->id)
            ->assertHasNoErrors()
            ->assertSee('En Proceso')
            ->assertSee('Finalizar Mantenimiento');

        $this->assertEquals('en_proceso', $mantenimiento->fresh()->estado);
        $this->assertEquals('en_mantenimiento', $this->equipo->fresh()->estado);
    }

    public function test_finalizar_mantenimiento_con_veredicto_conforme_reintegra_a_disponible(): void
    {
        $this->actingAs($this->panolero);

        $this->equipo->update(['estado' => 'en_mantenimiento']);

        $mantenimiento = Mantenimiento::create([
            'equipo_id' => $this->equipo->id,
            'tipo' => 'preventivo',
            'frecuencia' => 'semestral',
            'fecha_programada' => now()->toDateString(),
            'estado' => 'en_proceso',
        ]);

        Volt::test('pages.mantenimientos.index')
            ->call('abrirModalEjecutar', $mantenimiento->id)
            ->assertSet('mostrarModalEjecutar', true)
            ->assertSet('veredicto', 'conforme')
            ->set('observaciones_ejecucion', 'Limpieza profunda de componentes y validación de encendido.')
            ->call('registrarEjecucion')
            ->assertHasNoErrors()
            ->assertSet('mostrarModalEjecutar', false)
            ->assertSee('reintegrado a disponible');

        $this->assertEquals('realizado', $mantenimiento->fresh()->estado);
        $this->assertEquals('disponible', $this->equipo->fresh()->estado);
    }

    public function test_finalizar_mantenimiento_con_veredicto_falla_mantiene_en_mantenimiento_y_crea_falla(): void
    {
        Storage::fake('public');
        $this->actingAs($this->panolero);

        $this->equipo->update(['estado' => 'en_mantenimiento']);

        $mantenimiento = Mantenimiento::create([
            'equipo_id' => $this->equipo->id,
            'tipo' => 'preventivo',
            'frecuencia' => 'semestral',
            'fecha_programada' => now()->toDateString(),
            'estado' => 'en_proceso',
        ]);

        $archivo = UploadedFile::fake()->create('informe_falla.pdf', 150, 'application/pdf');

        Volt::test('pages.mantenimientos.index')
            ->call('abrirModalEjecutar', $mantenimiento->id)
            ->set('veredicto', 'falla')
            ->set('falla_descripcion', 'Falla en conector de alimentación detectada durante pruebas de encendido.')
            ->set('informe_tecnico', $archivo)
            ->call('registrarEjecucion')
            ->assertHasNoErrors()
            ->assertSet('mostrarModalEjecutar', false);

        $this->assertEquals('realizado', $mantenimiento->fresh()->estado);
        $this->assertEquals('en_mantenimiento', $this->equipo->fresh()->estado);

        $this->assertDatabaseHas('fallas', [
            'equipo_id' => $this->equipo->id,
            'descripcion' => 'Falla en conector de alimentación detectada durante pruebas de encendido.',
            'estado' => 'en_revision',
        ]);

        $falla = Falla::where('equipo_id', $this->equipo->id)->first();
        $this->assertNotNull($falla);
        $this->assertDatabaseHas('documentos', [
            'documentable_type' => Falla::class,
            'documentable_id' => $falla->id,
            'tipo' => 'informe_tecnico',
        ]);

        Volt::test('pages.mantenimientos.index')
            ->call('cambiarPestana', 'fallas')
            ->assertSee('Ver PDF');
    }

    public function test_finalizar_mantenimiento_con_veredicto_baja_pasa_a_pendiente_resolucion(): void
    {
        $this->actingAs($this->panolero);

        $this->equipo->update(['estado' => 'en_mantenimiento']);

        $mantenimiento = Mantenimiento::create([
            'equipo_id' => $this->equipo->id,
            'tipo' => 'preventivo',
            'frecuencia' => 'semestral',
            'fecha_programada' => now()->toDateString(),
            'estado' => 'en_proceso',
        ]);

        Volt::test('pages.mantenimientos.index')
            ->call('abrirModalEjecutar', $mantenimiento->id)
            ->set('veredicto', 'baja')
            ->set('baja_tipo', 'reemplazo')
            ->set('baja_motivo', 'Placa madre quemada sin repuestos en el mercado; conviene reponer por equipo nuevo.')
            ->call('registrarEjecucion')
            ->assertHasNoErrors()
            ->assertSet('mostrarModalEjecutar', false);

        $this->assertEquals('realizado', $mantenimiento->fresh()->estado);
        $this->assertEquals('pendiente_resolucion', $this->equipo->fresh()->estado);

        $this->assertDatabaseHas('bajas_reemplazos', [
            'equipo_id' => $this->equipo->id,
            'tipo' => 'reemplazo',
            'estado' => 'propuesta',
        ]);
    }

    public function test_iniciar_mantenimiento_vencido_no_se_revierte_al_rerenderizar(): void
    {
        $this->actingAs($this->panolero);

        $mantenimiento = Mantenimiento::create([
            'equipo_id' => $this->equipo->id,
            'tipo' => 'preventivo',
            'frecuencia' => 'semestral',
            'fecha_programada' => now()->subDays(2)->toDateString(),
            'estado' => 'vencido',
        ]);

        Volt::test('pages.mantenimientos.index')
            ->assertSee('Iniciar Mantenimiento')
            ->call('iniciarMantenimiento', $mantenimiento->id)
            ->assertHasNoErrors()
            ->assertSee('En Proceso')
            ->assertSee('Finalizar Mantenimiento');

        $this->assertEquals('en_proceso', $mantenimiento->fresh()->estado);
        $this->assertEquals('en_mantenimiento', $this->equipo->fresh()->estado);
    }
}
