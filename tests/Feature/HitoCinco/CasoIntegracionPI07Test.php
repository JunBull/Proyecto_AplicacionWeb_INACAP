<?php

namespace Tests\Feature\HitoCinco;

use App\Models\Carrera;
use App\Models\Equipo;
use App\Models\Insumo;
use App\Models\Ubicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CasoIntegracionPI07Test extends TestCase
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
            'codigo_barras' => 'EQ-RESP-001',
            'nombre' => 'Osciloscopio Digital de Laboratorio',
            'marca' => 'Tektronix',
            'modelo' => 'TBS1052B',
            'numero_serie' => 'SN-RESP-001',
            'criticidad' => 'alta',
            'estado' => 'disponible',
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        Insumo::create([
            'codigo_barras' => 'INS-RESP-001',
            'nombre' => 'Cables Patch Cord Cat6',
            'unidad' => 'unidad',
            'es_retornable' => false,
            'stock_actual' => 100,
            'stock_minimo' => 10,
            'ubicacion_id' => $this->ubicacion->id,
        ]);
    }

    /**
     * PI-07: Verificación de la metaetiqueta viewport responsive en todas las vistas base.
     */
    public function test_pi07_vistas_incorporan_viewport_responsive_para_dispositivos_moviles(): void
    {
        $response = $this->actingAs($this->docente)->get('/docente/solicitudes');
        $response->assertStatus(200);

        // Metaetiqueta obligatoria para escalado móvil según WCAG y estándares de adaptabilidad
        $response->assertSee('<meta name="viewport" content="width=device-width, initial-scale=1">', false);
    }

    /**
     * PI-07: Operación de solicitud anticipada desde navegador móvil (smartphone).
     */
    public function test_pi07_docente_opera_solicitudes_desde_navegador_movil_sin_perdida_de_informacion(): void
    {
        // Simular User-Agent móvil (iPhone / Safari)
        $mobileUserAgent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

        $response = $this->actingAs($this->docente)
            ->withHeaders(['User-Agent' => $mobileUserAgent])
            ->get('/docente/solicitudes');

        $response->assertStatus(200);

        // Comprobar elementos responsivos clave de Tailwind (sm:, md:, flex-col, overflow)
        $contenido = $response->getContent();
        $this->assertStringContainsString('sm:px-6', $contenido);
        $this->assertStringContainsString('max-w-7xl', $contenido);
        $this->assertStringContainsString('Portal Docente: Solicitud de Materiales', $contenido);

        // Se visualiza el botón de acción principal adaptado
        $this->assertStringContainsString('Nueva Solicitud', $contenido);
    }

    /**
     * PI-07: Operación de catálogo de equipos desde dispositivo tablet / desktop.
     */
    public function test_pi07_consulta_de_catalogo_adaptable_en_escritorio_y_tablet(): void
    {
        $tabletUserAgent = 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

        $response = $this->actingAs($this->panolero)
            ->withHeaders(['User-Agent' => $tabletUserAgent])
            ->get('/panol/equipos');

        $response->assertStatus(200);

        $contenido = $response->getContent();
        // Presencia de contenedor con scroll horizontal para tablas en pantallas compactas
        $this->assertStringContainsString('overflow-x-auto', $contenido);
        // Presencia del activo registrado
        $this->assertStringContainsString('Osciloscopio Digital de Laboratorio', $contenido);
        $this->assertStringContainsString('Tektronix', $contenido);
    }

    /**
     * PI-07: Operación de panel de despacho y devoluciones desde mesón de pañol.
     */
    public function test_pi07_panel_de_despacho_opera_con_grillas_adaptables_en_meson(): void
    {
        $response = $this->actingAs($this->panolero)->get('/panol/despacho');
        $response->assertStatus(200);

        $contenido = $response->getContent();
        $this->assertStringContainsString('grid', $contenido);
        $this->assertStringContainsString('gap-', $contenido);
        $this->assertStringContainsString('Gestión de Mesón: Despacho y Devoluciones', $contenido);
        $this->assertStringContainsString('wire:submit.prevent="escanearCodigo"', $contenido);
    }
}
