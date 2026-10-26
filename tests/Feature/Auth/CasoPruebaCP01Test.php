<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Caso de Prueba CP-01 (docs/especificacion_requerimientos_sistema.md):
 * Escenario: Iniciar sesión con credenciales válidas e inválidas.
 * Resultado Esperado: El acceso se permite únicamente con credenciales válidas y vigentes;
 * las credenciales incorrectas muestran mensajes genéricos de error sin revelar información interna
 * de la base de datos o estructura de cuentas.
 */
class CasoPruebaCP01Test extends TestCase
{
    use RefreshDatabase;

    public function test_cp01_acceso_permitido_con_credenciales_validas_y_vigentes(): void
    {
        $user = User::factory()->create([
            'email' => 'docente.valido@inacap.cl',
            'password' => Hash::make('ClaveSegura2026!'),
            'rol' => 'docente',
            'activo' => true,
        ]);

        $component = Volt::test('pages.auth.login')
            ->set('form.email', 'docente.valido@inacap.cl')
            ->set('form.password', 'ClaveSegura2026!');

        $component->call('login');

        $component
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_cp01_acceso_denegado_con_password_incorrecta_muestra_error_generico(): void
    {
        $user = User::factory()->create([
            'email' => 'admin.seguridad@inacap.cl',
            'password' => Hash::make('ClaveReal123!'),
            'activo' => true,
        ]);

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'ClaveIncorrecta999');

        $component->call('login');

        $component
            ->assertHasErrors(['form.email'])
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_cp01_acceso_denegado_con_correo_inexistente_muestra_error_generico(): void
    {
        $component = Volt::test('pages.auth.login')
            ->set('form.email', 'noexiste@inacap.cl')
            ->set('form.password', 'CualquierClave!');

        $component->call('login');

        $component
            ->assertHasErrors(['form.email'])
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_cp01_acceso_denegado_a_usuario_inactivo_sin_revelar_estado_cuenta(): void
    {
        $userInactivo = User::factory()->create([
            'email' => 'usuario.bloqueado@inacap.cl',
            'password' => Hash::make('ClaveValida123!'),
            'rol' => 'encargado_panol',
            'activo' => false, // Cuenta inactiva/no vigente
        ]);

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $userInactivo->email)
            ->set('form.password', 'ClaveValida123!');

        $component->call('login');

        $component
            ->assertHasErrors(['form.email'])
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_cp01_validacion_campos_obligatorios_formato_email(): void
    {
        $component = Volt::test('pages.auth.login')
            ->set('form.email', 'formato-invalido')
            ->set('form.password', '');

        $component->call('login');

        $component
            ->assertHasErrors(['form.email', 'form.password'])
            ->assertNoRedirect();

        $this->assertGuest();
    }
}
