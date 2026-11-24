<?php

namespace Tests\Feature\Docente;

use App\Models\Carrera;
use App\Models\Equipo;
use App\Models\Falla;
use App\Models\Ubicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class InformeNovedadesTest extends TestCase
{
    use RefreshDatabase;

    protected User $docente;

    protected Carrera $carrera;

    protected Ubicacion $laboratorio;

    protected Equipo $equipo;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->docente = User::factory()->create([
            'email' => 'docente.test@inacap.cl',
            'rol' => 'docente',
            'activo' => true,
        ]);

        $this->carrera = Carrera::create([
            'codigo' => 'INF-01',
            'nombre' => 'Ingeniería en Informática',
            'activa' => true,
        ]);

        $this->laboratorio = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Laboratorio L1 - Redes y Telecomunicaciones',
            'tipo' => 'laboratorio',
        ]);

        $this->equipo = Equipo::create([
            'codigo_barras' => 'EQ-PRU-001',
            'nombre' => 'Osciloscopio Digital 2 Canales',
            'marca' => 'Tektronix',
            'modelo' => 'TBS1052B',
            'numero_serie' => 'SN-TEK-9901',
            'ubicacion_id' => $this->laboratorio->id,
            'estado' => 'disponible',
        ]);
    }

    public function test_docente_puede_acceder_al_modulo_de_informe_de_novedades(): void
    {
        $response = $this->actingAs($this->docente)->get('/docente/novedades');

        $response->assertOk()
            ->assertSeeVolt('pages.docente.novedades');
    }

    public function test_docente_recibe_403_al_intentar_acceder_a_catalogo_de_equipos(): void
    {
        $response = $this->actingAs($this->docente)->get('/docente/equipos');

        $response->assertForbidden();
    }

    public function test_docente_reporta_novedad_exitosamente_desde_el_nuevo_modulo(): void
    {
        Queue::fake();

        $this->actingAs($this->docente);

        Volt::test('pages.docente.novedades')
            ->call('abrirModalReporte')
            ->set('tipoItem', 'equipo')
            ->set('codigoBarrasEquipo', $this->equipo->codigo_barras)
            ->set('ubicacionId', $this->laboratorio->id)
            ->set('descripcion', 'El equipo no enciende y presenta olor a quemado en el transformador.')
            ->set('severidad', 'alta')
            ->call('registrarNovedad')
            ->assertHasNoErrors()
            ->assertSee('¡Novedad');

        $this->assertDatabaseHas('fallas', [
            'equipo_id' => $this->equipo->id,
            'reportada_por' => $this->docente->id,
            'descripcion' => 'El equipo no enciende y presenta olor a quemado en el transformador.',
        ]);

        // El equipo cambia a en_mantenimiento
        $this->assertDatabaseHas('equipos', [
            'id' => $this->equipo->id,
            'estado' => 'en_mantenimiento',
        ]);
    }

    public function test_docente_puede_reportar_novedad_en_sala_con_edificio_y_sala_especifica(): void
    {
        Queue::fake();

        $this->actingAs($this->docente);

        Volt::test('pages.docente.novedades')
            ->call('abrirModalReporte')
            ->set('tipoItem', 'equipo')
            ->set('codigoBarrasEquipo', $this->equipo->codigo_barras)
            ->call('seleccionarTipoDestino', 'sala')
            ->set('edificio_destino', 'Edificio C')
            ->set('sala_destino', 'C101')
            ->set('descripcion', 'Proyector con parpadeo y falla intermitente en la sala de clases.')
            ->set('severidad', 'media')
            ->call('registrarNovedad')
            ->assertHasNoErrors()
            ->assertSee('¡Novedad');

        $this->assertDatabaseHas('fallas', [
            'equipo_id' => $this->equipo->id,
            'reportada_por' => $this->docente->id,
            'tipo_destino' => 'sala',
            'edificio_destino' => 'Edificio C',
            'espacio_destino' => 'Edificio C - C101',
            'descripcion' => 'Proyector con parpadeo y falla intermitente en la sala de clases.',
        ]);
    }

    public function test_docente_reporta_novedad_escaneando_codigo_valido_de_equipo(): void
    {
        Queue::fake();
        $this->actingAs($this->docente);

        Volt::test('pages.docente.novedades')
            ->call('abrirModalReporte')
            ->set('tipoItem', 'equipo')
            ->set('codigoBarrasEquipo', $this->equipo->codigo_barras)
            ->assertSet('equipoId', $this->equipo->id)
            ->assertSet('errorEquipo', null)
            ->assertSee($this->equipo->nombre)
            ->set('ubicacionId', $this->laboratorio->id)
            ->set('descripcion', 'Ruido anormal en el ventilador interno del osciloscopio.')
            ->call('registrarNovedad')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('fallas', [
            'equipo_id' => $this->equipo->id,
            'reportada_por' => $this->docente->id,
            'descripcion' => 'Ruido anormal en el ventilador interno del osciloscopio.',
        ]);

        $this->assertEquals('en_mantenimiento', $this->equipo->fresh()->estado);
    }

    public function test_docente_recibe_error_si_codigo_de_equipo_no_existe(): void
    {
        $this->actingAs($this->docente);

        Volt::test('pages.docente.novedades')
            ->call('abrirModalReporte')
            ->set('tipoItem', 'equipo')
            ->set('codigoBarrasEquipo', 'EQ-INEXISTENTE-888')
            ->assertSet('errorEquipo', "El código 'EQ-INEXISTENTE-888' no está registrado en el catálogo de equipos.")
            ->assertSet('equipoId', null)
            ->call('registrarNovedad')
            ->assertHasErrors(['codigoBarrasEquipo']);
    }

    public function test_docente_recibe_error_si_equipo_ya_esta_dado_de_baja(): void
    {
        $this->equipo->update(['estado' => 'dado_de_baja']);
        $this->actingAs($this->docente);

        Volt::test('pages.docente.novedades')
            ->call('abrirModalReporte')
            ->set('tipoItem', 'equipo')
            ->set('codigoBarrasEquipo', $this->equipo->codigo_barras)
            ->assertSee('ya fue dado de baja formalmente')
            ->assertSet('equipoId', null)
            ->call('registrarNovedad')
            ->assertHasErrors(['codigoBarrasEquipo']);
    }

    public function test_docente_reporta_novedad_tipo_otro_sin_requerir_equipo_generando_pdf_y_correo(): void
    {
        Queue::fake();
        $this->actingAs($this->docente);

        Volt::test('pages.docente.novedades')
            ->call('abrirModalReporte')
            ->set('tipoItem', 'otro')
            ->assertSet('equipoId', null)
            ->call('seleccionarTipoDestino', 'sala')
            ->set('edificio_destino', 'Edificio A')
            ->set('sala_destino', 'A201')
            ->set('descripcion', 'Enchufes del mesón central sin corriente y con signos de chispazo eléctrico.')
            ->set('severidad', 'alta')
            ->call('registrarNovedad')
            ->assertHasNoErrors()
            ->assertSee('¡Novedad');

        $falla = Falla::where('reportada_por', $this->docente->id)
            ->whereNull('equipo_id')
            ->whereNull('insumo_id')
            ->first();

        $this->assertNotNull($falla);
        $this->assertEquals('Edificio A - A201', $falla->espacio_destino);
        $this->assertStringContainsString('Enchufes del mesón central', $falla->descripcion);

        // Verifica almacenamiento del PDF
        Storage::disk('public')->assertExists("incidencias/Reporte_Incidencia_{$falla->id}.pdf");
    }
}
