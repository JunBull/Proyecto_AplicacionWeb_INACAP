<?php

namespace Tests\Feature\Docente;

use App\Models\Carrera;
use App\Models\Equipo;
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
            ->set('equipoId', $this->equipo->id)
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
            ->set('equipoId', $this->equipo->id)
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
}
