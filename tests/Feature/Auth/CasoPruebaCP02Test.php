<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Caso de Prueba CP-02 (docs/especificacion_requerimientos_sistema.md):
 * Escenario: Intentar operaciones y acceso a documentos desde un rol no autorizado.
 * Resultado Esperado: El servidor rechaza la acción mediante código HTTP 403 Forbidden
 * o redirección segura, impidiendo consultar, descargar o modificar información y documentos restringidos.
 */
class CasoPruebaCP02Test extends TestCase
{
    use RefreshDatabase;

    public function test_cp02_usuario_no_autenticado_es_redirigido_al_login(): void
    {
        $responseAdmin = $this->get('/admin/usuarios');
        $responseAdmin->assertRedirect(route('login', absolute: false));

        $responsePanol = $this->get('/panol/inventario');
        $responsePanol->assertRedirect(route('login', absolute: false));
    }

    public function test_cp02_docente_recibe_403_al_intentar_acceder_a_gestion_de_usuarios_admin(): void
    {
        $docente = User::factory()->docente()->create();

        $response = $this->actingAs($docente)->get('/admin/usuarios');

        $response->assertForbidden();
    }

    public function test_cp02_encargado_panol_recibe_403_al_intentar_acceder_a_gestion_de_usuarios_admin(): void
    {
        $panolero = User::factory()->panolero()->create();

        $response = $this->actingAs($panolero)->get('/admin/usuarios');

        $response->assertForbidden();
    }

    public function test_cp02_administrador_accede_exitosamente_a_gestion_de_usuarios(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/admin/usuarios');

        $response->assertOk();
        $response->assertJson([
            'status' => 'ok',
            'modulo' => 'gestion_usuarios',
        ]);
    }

    public function test_cp02_docente_recibe_403_al_intentar_acceder_a_despacho_de_panol(): void
    {
        $docente = User::factory()->docente()->create();

        $response = $this->actingAs($docente)->get('/panol/despacho');

        $response->assertForbidden();
    }

    public function test_cp02_encargado_panol_accede_exitosamente_a_despacho_de_panol(): void
    {
        $panolero = User::factory()->panolero()->create();

        $response = $this->actingAs($panolero)->get('/panol/despacho');

        $response->assertOk();
        $response->assertSeeLivewire('pages.despacho.index');
    }

    public function test_cp02_docente_no_puede_acceder_a_documentos_administrativos_restringidos(): void
    {
        $docente = User::factory()->docente()->create();

        $response = $this->actingAs($docente)->get('/admin/documentos/105');

        $response->assertForbidden();
    }

    public function test_cp02_administrador_puede_acceder_a_documentos_administrativos(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/admin/documentos/105');

        $response->assertOk();
        $response->assertJson([
            'status' => 'ok',
            'documento_id' => '105',
        ]);
    }
}
