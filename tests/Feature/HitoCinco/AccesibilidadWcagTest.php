<?php

namespace Tests\Feature\HitoCinco;

use App\Models\Carrera;
use App\Models\Equipo;
use App\Models\Ubicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccesibilidadWcagTest extends TestCase
{
    use RefreshDatabase;

    protected User $docente;

    protected User $panolero;

    protected Carrera $carrera;

    protected Ubicacion $ubicacion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->carrera = Carrera::create([
            'nombre' => 'Ingeniería en Informática',
            'codigo' => 'INF-01',
            'activa' => true,
        ]);

        $this->ubicacion = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Pañol Central',
            'codigo' => 'PAN-01',
            'tipo' => 'panol',
        ]);

        $this->docente = User::factory()->create([
            'name' => 'Prof. Juan Pérez',
            'email' => 'juan.perez@docente.inacap.cl',
            'rol' => 'docente',
            'activo' => true,
        ]);

        $this->panolero = User::factory()->create([
            'name' => 'Carlos Encargado',
            'email' => 'carlos.panol@inacap.cl',
            'rol' => 'encargado_panol',
            'activo' => true,
        ]);

        Equipo::create([
            'codigo_barras' => 'EQ-WCAG-001',
            'nombre' => 'Notebook Lenovo ThinkPad',
            'marca' => 'Lenovo',
            'modelo' => 'ThinkPad T14',
            'numero_serie' => 'SN-WCAG-001',
            'criticidad' => 'alta',
            'estado' => 'disponible',
            'ubicacion_id' => $this->ubicacion->id,
        ]);
    }

    /**
     * WCAG 2.4.1 (Nivel A) - Evitar bloques repetitivos (Skip to content).
     */
    public function test_wcag_241_dispone_de_enlace_accesible_para_saltar_al_contenido_principal(): void
    {
        $response = $this->actingAs($this->docente)->get('/docente/solicitudes');
        $response->assertStatus(200);

        $contenido = $response->getContent();
        $this->assertStringContainsString('href="#main-content"', $contenido);
        $this->assertStringContainsString('id="main-content"', $contenido);
        $this->assertStringContainsString('sr-only focus:not-sr-only', $contenido);
    }

    /**
     * WCAG 1.4.4 (Nivel AA) - Redimensionamiento de texto y viewport no restrictivo.
     */
    public function test_wcag_144_viewport_no_bloquea_zoom_ni_escalado_del_usuario(): void
    {
        $response = $this->actingAs($this->docente)->get('/dashboard');
        $response->assertStatus(200);

        $contenido = $response->getContent();
        $this->assertStringContainsString('<meta name="viewport" content="width=device-width, initial-scale=1">', $contenido);
        // Prohibido bloquear el zoom según WCAG 1.4.4 (user-scalable=no / maximum-scale=1.0)
        $this->assertStringNotContainsString('user-scalable=no', $contenido);
        $this->assertStringNotContainsString('maximum-scale=1', $contenido);
    }

    /**
     * WCAG 2.4.7 (Nivel AA) - Visibilidad del foco en navegación por teclado.
     */
    public function test_wcag_247_elementos_interactivos_poseen_indicadores_de_foco_visibles(): void
    {
        $response = $this->actingAs($this->docente)->get('/docente/solicitudes');
        $response->assertStatus(200);

        $contenido = $response->getContent();
        // Verificación de clases Tailwind de foco resaltado
        $this->assertStringContainsString('focus:ring-', $contenido);
        $this->assertStringContainsString('focus:outline-none', $contenido);
    }

    /**
     * WCAG 1.3.1 (Nivel A) - Estructuración semántica en tablas y modales.
     */
    public function test_wcag_131_tablas_y_vistas_mantienen_semantica_accesible(): void
    {
        $response = $this->actingAs($this->panolero)->get('/panol/equipos');
        $response->assertStatus(200);

        $contenido = $response->getContent();
        $this->assertStringContainsString('<table', $contenido);
        $this->assertStringContainsString('<thead', $contenido);
        $this->assertStringContainsString('<th', $contenido);
        $this->assertStringContainsString('<tbody', $contenido);
    }

    /**
     * WCAG 4.1.2 (Nivel A) - Pantallas de error presentan roles de accesibilidad (role="alert").
     */
    public function test_wcag_412_pantallas_de_error_definen_role_alert(): void
    {
        $response403 = $this->view('errors.403');
        $response403->assertSee('role="alert"', false);
        $response403->assertSee('aria-labelledby="error-title"', false);

        $response500 = $this->view('errors.500');
        $response500->assertSee('role="alert"', false);
        $response500->assertSee('aria-labelledby="error-title"', false);
    }
}
