<?php

namespace Tests\Feature\Equipos;

use App\Models\Carrera;
use App\Models\Categoria;
use App\Models\Equipo;
use App\Models\Mantenimiento;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\EquipoService;
use Database\Seeders\CategoriasEquiposSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CatalogoEquiposCategoriasYHojaVidaTest extends TestCase
{
    use RefreshDatabase;

    protected User $panolero;

    protected User $docente;

    protected Ubicacion $ubicacion;

    protected function setUp(): void
    {
        parent::setUp();

        $carrera = Carrera::create([
            'codigo' => 'INF-TEST',
            'nombre' => 'Ingeniería en Informática',
            'activa' => true,
        ]);

        $this->ubicacion = Ubicacion::create([
            'carrera_id' => $carrera->id,
            'nombre' => 'LTEL',
            'tipo' => 'laboratorio',
        ]);

        $this->panolero = User::factory()->create([
            'email' => 'panolero.cat@inacap.cl',
            'rol' => 'encargado_panol',
        ]);

        $this->docente = User::factory()->create([
            'email' => 'docente.cat@inacap.cl',
            'rol' => 'docente',
        ]);

        $this->seed(CategoriasEquiposSeeder::class);
    }

    /**
     * Verifica que existan las 7 categorías institucionales y que la vista las liste agrupadas con contadores.
     */
    public function test_catalogo_muestra_las_7_categorias_con_contadores_y_equipos(): void
    {
        $this->assertEquals(7, Categoria::count());

        $component = Volt::actingAs($this->panolero)
            ->test('pages.equipos.index');

        $component->assertStatus(200);

        // Verifica la presencia de los nombres de las 7 categorías
        $component->assertSee('Cómputo Móvil')
            ->assertSee('Redes y Telecomunicaciones')
            ->assertSee('Instrumentación Electrónica y Sensores')
            ->assertSee('Sistemas Embebidos, IoT y Robótica')
            ->assertSee('Impresión, Captura y Punto de Venta (Auto-ID)')
            ->assertSee('Herramientas de Taller y Redes')
            ->assertSee('Audiovisual y Realidad Extendida');

        // Verifica que aparezca el formato de contador de disponibilidad
        $component->assertSee('disponibles')
            ->assertSee('total');
    }

    /**
     * Verifica que el registro de equipo asigne la categoría y deduzca automáticamente la criticidad.
     */
    public function test_registro_nuevo_equipo_asigna_categoria_y_default_criticidad(): void
    {
        $categoriaRedes = Categoria::where('slug', 'redes-telecomunicaciones')->firstOrFail();

        $datos = [
            'codigo_barras' => 'EQ-TEST-NET-99',
            'nombre' => 'Switch Administrable Gigabit Ethernet 48 Puertos',
            'marca' => 'Cisco Systems',
            'modelo' => 'Catalyst 3850',
            'numero_serie' => 'SN-NET-3850-99',
            'categoria_id' => $categoriaRedes->id,
            'ubicacion_id' => $this->ubicacion->id,
        ];

        $equipoService = app(EquipoService::class);
        $equipo = $equipoService->registrarEquipo($datos, $this->panolero);

        $this->assertInstanceOf(Equipo::class, $equipo);
        $this->assertEquals($categoriaRedes->id, $equipo->categoria_id);
        // La criticidad sugerida de redes es 'alta'
        $this->assertEquals('alta', $equipo->criticidad);
        $this->assertEquals('disponible', $equipo->estado);

        $this->assertDatabaseHas('equipos', [
            'codigo_barras' => 'EQ-TEST-NET-99',
            'categoria_id' => $categoriaRedes->id,
            'criticidad' => 'alta',
        ]);
    }

    /**
     * Verifica que la acción "Hoja de Vida" abra el modal técnico con métricas y SIN botón de registrar mantenimiento.
     */
    public function test_hoja_de_vida_muestra_metricas_correctas_y_sin_boton_registrar_mantenimiento(): void
    {
        $equipo = Equipo::where('codigo_barras', 'EQ-LNV-T14-01')->firstOrFail();

        $component = Volt::actingAs($this->panolero)
            ->test('pages.equipos.index')
            ->call('abrirHojaVida', $equipo->id);

        $component->assertSet('mostrarModalHojaVida', true)
            ->assertSet('equipoHojaVidaId', $equipo->id)
            ->assertSee('Hoja de Vida de Mantenimiento')
            ->assertSee('Notebook Lenovo ThinkPad T14')
            ->assertSee('SN-LNV-T14-001')
            ->assertSee('Total Mantenimientos')
            ->assertSee('Último Preventivo')
            ->assertSee('Próximo Programado')
            ->assertSee('Historial de Intervenciones y Fallas')
            ->assertSee('Limpieza interna de ventilador, cambio de pasta térmica')
            // Requerimiento explícito: NO debe tener botón para registrar mantenimiento
            ->assertDontSee('+ Registrar Mantenimiento')
            ->assertDontSee('Registrar Mantenimiento');
    }

    /**
     * Verifica que la búsqueda reactiva mantenga abiertas las categorías coincidentes.
     */
    public function test_busqueda_reactiva_encuentra_activos_especificos(): void
    {
        $component = Volt::actingAs($this->panolero)
            ->test('pages.equipos.index')
            ->set('buscar', 'ThinkPad');

        $component->assertStatus(200)
            ->assertSee('Notebook Lenovo ThinkPad T14')
            ->assertDontSee('Switch Cisco Catalyst');
    }

    /**
     * Verifica que los docentes no tengan acceso al catálogo ni a la hoja de vida.
     */
    public function test_docente_no_puede_acceder_al_catalogo_de_equipos(): void
    {
        $response = $this->actingAs($this->docente)->get('/panol/equipos');
        $response->assertStatus(403);
    }
}
