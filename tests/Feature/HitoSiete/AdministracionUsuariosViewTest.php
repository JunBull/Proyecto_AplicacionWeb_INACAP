<?php

namespace Tests\Feature\HitoSiete;

use App\Models\Carrera;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AdministracionUsuariosViewTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $panolero;

    protected User $docente;

    protected Carrera $carrera;

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

        $this->carrera = Carrera::create([
            'codigo' => 'INF-ING',
            'nombre' => 'Ingeniería en Informática',
            'activa' => true,
        ]);
    }

    public function test_administrador_renderiza_panel_gestion_de_usuarios_correctamente(): void
    {
        Volt::actingAs($this->admin)
            ->test('pages.administracion.usuarios')
            ->assertOk()
            ->assertSee('Gestión de Usuarios y Roles')
            ->assertSee('Administrador Sistema')
            ->assertSee('Encargado Pañol')
            ->assertSee('Docente Prueba');
    }

    public function test_docente_no_puede_acceder_a_componente_de_gestion_de_usuarios(): void
    {
        Volt::actingAs($this->docente)
            ->test('pages.administracion.usuarios')
            ->assertForbidden();
    }

    public function test_encargado_panol_no_puede_acceder_a_componente_de_gestion_de_usuarios(): void
    {
        Volt::actingAs($this->panolero)
            ->test('pages.administracion.usuarios')
            ->assertForbidden();
    }

    public function test_busqueda_y_filtro_por_rol_de_usuarios(): void
    {
        Volt::actingAs($this->admin)
            ->test('pages.administracion.usuarios')
            ->set('buscar', 'Docente')
            ->assertSee('Docente Prueba')
            ->assertDontSee('Encargado Pañol')
            ->set('buscar', '')
            ->set('filtroRol', 'encargado_panol')
            ->assertSee('Encargado Pañol')
            ->assertDontSee('Docente Prueba');
    }

    public function test_administrador_crea_nuevo_usuario_desde_formulario(): void
    {
        Volt::actingAs($this->admin)
            ->test('pages.administracion.usuarios')
            ->call('abrirModalCrear')
            ->set('name', 'Nuevo Docente Informática')
            ->set('email', 'nuevo.docente@inacap.cl')
            ->set('password', 'ClaveSegura2026!')
            ->set('rol', 'docente')
            ->set('carrerasSeleccionadas', [$this->carrera->id])
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('usuarios', [
            'email' => 'nuevo.docente@inacap.cl',
            'nombre' => 'Nuevo Docente Informática',
            'rol' => 'docente',
        ]);
    }

    public function test_administrador_alterna_estado_activo_y_no_puede_desactivarse_a_si_mismo(): void
    {
        Volt::actingAs($this->admin)
            ->test('pages.administracion.usuarios')
            ->call('toggleActivo', $this->docente->id);

        $this->assertFalse($this->docente->fresh()->activo);

        Volt::actingAs($this->admin)
            ->test('pages.administracion.usuarios')
            ->call('toggleActivo', $this->admin->id);

        $this->assertTrue($this->admin->fresh()->activo);
    }
}
