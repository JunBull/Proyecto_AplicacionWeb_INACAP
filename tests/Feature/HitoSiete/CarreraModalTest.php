<?php

namespace Tests\Feature\HitoSiete;

use App\Models\Carrera;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CarreraModalTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $panolero;

    protected User $docente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'nombre' => 'Administrador Sistema',
            'email' => 'admin@inacap.cl',
            'rol' => 'administrador',
            'activo' => true,
        ]);

        $this->panolero = User::factory()->create([
            'nombre' => 'Encargado Pañol',
            'email' => 'panolero@inacap.cl',
            'rol' => 'encargado_panol',
            'activo' => true,
        ]);

        $this->docente = User::factory()->create([
            'nombre' => 'Docente Prueba',
            'email' => 'docente@inacap.cl',
            'rol' => 'docente',
            'activo' => true,
        ]);
    }

    public function test_administrador_puede_abrir_modal_y_crear_nueva_carrera(): void
    {
        $this->actingAs($this->admin);

        Volt::test('pages.administracion.carreras')
            ->assertSee('Nueva Carrera')
            ->call('abrirModalCarrera')
            ->assertSet('mostrarModalCarrera', true)
            ->assertSee('Nueva Carrera Académica')
            ->set('codigoCarrera', 'TEL-01')
            ->set('nombreCarrera', 'Telecomunicaciones y Redes')
            ->set('activaCarrera', true)
            ->call('guardarCarrera')
            ->assertHasNoErrors()
            ->assertSet('mostrarModalCarrera', false)
            ->assertSee('Carrera académica guardada correctamente.');

        $this->assertDatabaseHas('carreras', [
            'codigo' => 'TEL-01',
            'nombre' => 'Telecomunicaciones y Redes',
            'activa' => true,
        ]);
    }

    public function test_valida_campos_requeridos_y_codigo_unico_en_carrera(): void
    {
        $this->actingAs($this->admin);

        Carrera::create([
            'codigo' => 'INF-ING',
            'nombre' => 'Ingeniería en Informática',
            'activa' => true,
        ]);

        Volt::test('pages.administracion.carreras')
            ->call('abrirModalCarrera')
            ->set('codigoCarrera', '')
            ->set('nombreCarrera', '')
            ->call('guardarCarrera')
            ->assertHasErrors(['codigoCarrera' => 'required', 'nombreCarrera' => 'required']);

        Volt::test('pages.administracion.carreras')
            ->call('abrirModalCarrera')
            ->set('codigoCarrera', 'INF-ING')
            ->set('nombreCarrera', 'Otra Informática')
            ->call('guardarCarrera')
            ->assertHasErrors(['codigoCarrera' => 'unique']);
    }

    public function test_administrador_puede_editar_carrera_existente(): void
    {
        $this->actingAs($this->admin);

        $carrera = Carrera::create([
            'codigo' => 'MEC-01',
            'nombre' => 'Mecánica Automotriz',
            'activa' => true,
        ]);

        Volt::test('pages.administracion.carreras')
            ->call('editarCarrera', $carrera->id)
            ->assertSet('mostrarModalCarrera', true)
            ->assertSet('carreraId', $carrera->id)
            ->assertSet('codigoCarrera', 'MEC-01')
            ->assertSet('nombreCarrera', 'Mecánica Automotriz')
            ->assertSet('activaCarrera', true)
            ->assertSee('Editar Carrera Académica')
            ->set('nombreCarrera', 'Mecánica y Electromovilidad')
            ->call('guardarCarrera')
            ->assertHasNoErrors()
            ->assertSet('mostrarModalCarrera', false);

        $this->assertDatabaseHas('carreras', [
            'id' => $carrera->id,
            'codigo' => 'MEC-01',
            'nombre' => 'Mecánica y Electromovilidad',
        ]);
    }

    public function test_usuarios_no_administradores_no_pueden_acceder(): void
    {
        $this->actingAs($this->panolero);

        $response = $this->get(route('admin.carreras'));
        $response->assertForbidden();

        $this->actingAs($this->docente);

        $response = $this->get(route('admin.carreras'));
        $response->assertForbidden();
    }
}
