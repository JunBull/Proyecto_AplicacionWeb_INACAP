<?php

namespace Tests\Feature\Equipos;

use App\Models\Carrera;
use App\Models\Categoria;
use App\Models\Equipo;
use App\Models\Mantenimiento;
use App\Models\Subcategoria;
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
     * Verifica que existan las 6 categorías oficiales y 22 subcategorías, y que la vista las liste con contadores.
     */
    public function test_catalogo_muestra_las_6_categorias_con_contadores_y_equipos(): void
    {
        $this->assertEquals(6, Categoria::count());
        $this->assertEquals(22, Subcategoria::count());

        $component = Volt::actingAs($this->panolero)
            ->test('pages.equipos.index');

        $component->assertStatus(200);

        // Verifica la presencia de los nombres de las 6 categorías oficiales
        $component->assertSee('Cómputo Móvil')
            ->assertSee('Redes y Telecomunicaciones')
            ->assertSee('Instrumentación Electrónica')
            ->assertSee('Sistemas Embebidos, IoT y Robótica')
            ->assertSee('Impresión, Captura y Punto de Venta (Auto-ID)')
            ->assertSee('Audiovisual y Realidad Extendida')
            ->assertDontSee('Herramientas de Taller y Redes');

        // Verifica que aparezca el formato de contador de disponibilidad
        $component->assertSee('disponibles')
            ->assertSee('total');
    }

    /**
     * Verifica que el registro de equipo asigne categoría, subcategoría y deduzca automáticamente la criticidad.
     */
    public function test_registro_nuevo_equipo_asigna_categoria_subcategoria_y_default_criticidad(): void
    {
        $categoriaRedes = Categoria::where('slug', 'redes-telecomunicaciones')->firstOrFail();
        $subcategoriaSwitch = Subcategoria::where('slug', 'switches')->firstOrFail();

        $equipoService = app(EquipoService::class);
        $codigoSugerido = $equipoService->sugerirCodigoBarrasPorSubcategoria($subcategoriaSwitch->id, 'Cisco Systems', 'Catalyst 3850');
        $this->assertEquals('EQ-SW-CSC-3850-01', $codigoSugerido);

        $subcategoriaTablets = Subcategoria::where('slug', 'tablets')->firstOrFail();
        $codigoSiguienteTablet = $equipoService->sugerirCodigoBarrasPorSubcategoria($subcategoriaTablets->id, 'Lenovo', 'Tab M11');
        $this->assertEquals('EQ-TAB-LNV-M11-02', $codigoSiguienteTablet);

        $this->assertDatabaseHas('equipos', ['codigo_barras' => 'EQ-TAB-APL-IPAD-01']);
        $this->assertDatabaseHas('equipos', ['codigo_barras' => 'EQ-VR-HTC-VIVE-01']);
        $this->assertDatabaseMissing('equipos', ['codigo_barras' => 'EQ-CRIMP-01']);

        $datos = [
            'codigo_barras' => 'EQ-TEST-NET-99',
            'nombre' => 'Switch Administrable Gigabit Ethernet 48 Puertos',
            'marca' => 'Cisco Systems',
            'modelo' => 'Catalyst 3850',
            'numero_serie' => 'SN-NET-3850-99',
            'categoria_id' => $categoriaRedes->id,
            'subcategoria_id' => $subcategoriaSwitch->id,
            'ubicacion_id' => $this->ubicacion->id,
        ];

        $equipo = $equipoService->registrarEquipo($datos, $this->panolero);

        $this->assertInstanceOf(Equipo::class, $equipo);
        $this->assertEquals($categoriaRedes->id, $equipo->categoria_id);
        $this->assertEquals($subcategoriaSwitch->id, $equipo->subcategoria_id);
        // La criticidad sugerida de redes es 'alta'
        $this->assertEquals('alta', $equipo->criticidad);
        $this->assertEquals('disponible', $equipo->estado);
        $this->assertFalse($equipo->etiqueta_impresa);

        $this->assertDatabaseHas('equipos', [
            'codigo_barras' => 'EQ-TEST-NET-99',
            'categoria_id' => $categoriaRedes->id,
            'subcategoria_id' => $subcategoriaSwitch->id,
            'criticidad' => 'alta',
        ]);
    }

    /**
     * Verifica que la acción "Hoja de Vida" abra el modal técnico con métricas y SIN botón de registrar mantenimiento.
     */
    public function test_hoja_de_vida_muestra_metricas_correctas_y_sin_boton_registrar_mantenimiento(): void
    {
        $equipo = Equipo::where('codigo_barras', 'EQ-NB-LNV-T14-01')->firstOrFail();

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
     * Verifica que las categorías inicien colapsadas por defecto y no se desplieguen automáticamente al abrir o guardar un nuevo equipo.
     */
    public function test_categorias_inician_cerradas_y_no_se_abren_al_agregar_nuevo_equipo(): void
    {
        $categoriaRedes = Categoria::where('slug', 'redes-telecomunicaciones')->firstOrFail();

        $component = Volt::actingAs($this->panolero)
            ->test('pages.equipos.index')
            ->assertSet('categoriasAbiertas', [])
            ->assertSee('aria-expanded="false"', false)
            ->assertDontSee('aria-expanded="true"', false);

        // Abrir modal de nuevo equipo no debe desplegar categorías
        $component->call('abrirModalCrear')
            ->assertSet('mostrarModalCrear', true)
            ->assertSet('categoriasAbiertas', [])
            ->assertDontSee('aria-expanded="true"', false);

        // Expandir y luego colapsar todas, seguido de abrir modal y guardar equipo, debe mantenerlas cerradas
        $component->call('expandirTodas')
            ->assertNotSet('categoriasAbiertas', [])
            ->call('colapsarTodas')
            ->assertSet('categoriasAbiertas', [])
            ->call('abrirModalCrear')
            ->assertSet('categoriasAbiertas', [])
            ->set('nombre', 'Router Empresarial Cisco ISR 4321')
            ->set('codigo_barras', 'EQ-TEST-RTR-01')
            ->set('numero_serie', 'SN-RTR-4321-01')
            ->set('marca', 'Cisco')
            ->set('modelo', 'ISR 4321')
            ->set('categoria_id', $categoriaRedes->id)
            ->set('ubicacion_id', $this->ubicacion->id)
            ->call('guardarEquipo')
            ->assertSet('mostrarModalCrear', false)
            ->assertSet('categoriasAbiertas', [])
            ->assertDontSee('aria-expanded="true"', false);
    }

    /**
     * Verifica Escenario A (familia existente autocompletada y correlativo) y Escenario B (+ Otro / Nuevo modelo + Guardar y agregar otro igual + S/N único).
     */
    public function test_modal_nuevo_equipo_escenario_a_y_escenario_b_con_rafaga_y_serie_unica(): void
    {
        $categoriaComputo = Categoria::where('slug', 'computo-movil')->firstOrFail();
        $subTablets = Subcategoria::where('slug', 'tablets')->firstOrFail();

        // Escenario A: seleccionar familia existente EQ-TAB-LNV-M11
        $component = Volt::actingAs($this->panolero)
            ->test('pages.equipos.index')
            ->call('abrirModalCrear')
            ->set('categoria_id', $categoriaComputo->id)
            ->set('subcategoria_id', $subTablets->id)
            ->assertSet('prefijoSubcategoria', 'EQ-TAB')
            ->set('familiaSeleccionada', 'EQ-TAB-LNV-M11')
            ->assertSet('nombre', 'Tablet Lenovo Tab M11 128GB Wi-Fi')
            ->assertSet('marca', 'Lenovo')
            ->assertSet('modelo', 'Tab M11')
            ->assertSet('codigo_barras', 'EQ-TAB-LNV-M11-02')
            ->set('numero_serie', 'HA19X02')
            ->set('ubicacion_id', $this->ubicacion->id)
            ->call('guardarEquipo')
            ->assertHasNoErrors()
            ->assertSet('mostrarModalCrear', false);

        $this->assertDatabaseHas('equipos', [
            'codigo_barras' => 'EQ-TAB-LNV-M11-02',
            'numero_serie' => 'HA19X02',
            'etiqueta_impresa' => false,
        ]);

        // Intentar registrar otro equipo con S/N duplicado debe ser rechazado
        Volt::actingAs($this->panolero)
            ->test('pages.equipos.index')
            ->call('abrirModalCrear')
            ->set('categoria_id', $categoriaComputo->id)
            ->set('subcategoria_id', $subTablets->id)
            ->set('familiaSeleccionada', 'EQ-TAB-LNV-M11')
            ->set('numero_serie', 'ha19x02')
            ->set('ubicacion_id', $this->ubicacion->id)
            ->call('guardarEquipo')
            ->assertHasErrors(['numero_serie']);

        // Escenario B: + Otro / Nuevo modelo -> constructor EQ-TAB- + SAM-S9 -> EQ-TAB-SAM-S9-01
        // Y uso de "Guardar y agregar otro igual" para avanzar automáticamente a EQ-TAB-SAM-S9-02
        $componentB = Volt::actingAs($this->panolero)
            ->test('pages.equipos.index')
            ->call('abrirModalCrear')
            ->set('categoria_id', $categoriaComputo->id)
            ->set('subcategoria_id', $subTablets->id)
            ->set('familiaSeleccionada', '__nuevo__')
            ->set('sufijoCodigoNuevo', 'sam-s9')
            ->assertSet('codigo_barras', 'EQ-TAB-SAM-S9-01')
            ->set('nombre', 'Tablet Samsung Galaxy Tab S9 256GB')
            ->set('marca', 'Samsung')
            ->set('modelo', 'Galaxy Tab S9')
            ->set('numero_serie', 'SN-SAMS9-001')
            ->set('ubicacion_id', $this->ubicacion->id)
            ->call('guardarEquipo', true)
            ->assertHasNoErrors()
            ->assertSet('mostrarModalCrear', true)
            ->assertSet('familiaSeleccionada', 'EQ-TAB-SAM-S9')
            ->assertSet('codigo_barras', 'EQ-TAB-SAM-S9-02')
            ->assertSet('numero_serie', '')
            ->set('numero_serie', 'SN-SAMS9-002')
            ->call('guardarEquipo')
            ->assertHasNoErrors()
            ->assertSet('mostrarModalCrear', false);

        $this->assertDatabaseHas('equipos', ['codigo_barras' => 'EQ-TAB-SAM-S9-01', 'numero_serie' => 'SN-SAMS9-001']);
        $this->assertDatabaseHas('equipos', ['codigo_barras' => 'EQ-TAB-SAM-S9-02', 'numero_serie' => 'SN-SAMS9-002']);

        // Al reabrir modal en Tablets, EQ-TAB-SAM-S9 ya aparece como familia existente en Escenario A
        Volt::actingAs($this->panolero)
            ->test('pages.equipos.index')
            ->call('abrirModalCrear')
            ->set('categoria_id', $categoriaComputo->id)
            ->set('subcategoria_id', $subTablets->id)
            ->assertSee('EQ-TAB-SAM-S9 — Tablet Samsung Galaxy Tab S9 256GB');
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
