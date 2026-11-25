<?php

namespace Tests\Feature\Panol;

use App\Models\Carrera;
use App\Models\Categoria;
use App\Models\Equipo;
use App\Models\Insumo;
use App\Models\Subcategoria;
use App\Models\Ubicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PanoleroEtiquetasOptimizacionTest extends TestCase
{
    use RefreshDatabase;

    private User $panolero;

    private Carrera $carrera;

    private Ubicacion $ubicacion;

    private Equipo $equipo1;

    private Equipo $equipo2;

    private Insumo $insumo1;

    private Insumo $insumo2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->panolero = User::factory()->create([
            'name' => 'Pañolero Optimizacion Test',
            'email' => 'panolero.opt@inacap.cl',
            'rol' => 'encargado_panol',
            'activo' => true,
        ]);

        $this->carrera = Carrera::create([
            'codigo' => 'INF-01',
            'nombre' => 'Ingeniería en Informática',
            'activa' => true,
        ]);

        $this->ubicacion = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Laboratorio Redes A-101',
            'tipo' => 'laboratorio',
        ]);

        $categoria = Categoria::create([
            'nombre' => 'Conectividad y Redes',
            'slug' => 'conectividad-y-redes',
            'descripcion' => 'Equipos de routing y switching',
        ]);

        $this->equipo1 = Equipo::create([
            'codigo_barras' => 'EQ-CISCO-SWITCH-01',
            'nombre' => 'Switch Cisco Catalyst 2960',
            'marca' => 'Cisco',
            'modelo' => 'Catalyst 2960-X',
            'numero_serie' => 'SN-CSCO-2960X',
            'estado' => 'disponible',
            'categoria_id' => $categoria->id,
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        $this->equipo2 = Equipo::create([
            'codigo_barras' => 'EQ-ROUTER-MIKROTIK',
            'nombre' => 'Router MikroTik CCR1009',
            'marca' => 'MikroTik',
            'modelo' => 'CCR1009-7G-1C-1S+',
            'numero_serie' => 'SN-MIKRO-1009',
            'estado' => 'disponible',
            'categoria_id' => $categoria->id,
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        $this->insumo1 = Insumo::create([
            'codigo_barras' => 'INS-RJ45-PANDUIT',
            'nombre' => 'Conectores RJ45 Cat6 Panduit',
            'unidad' => 'bolsa',
            'es_retornable' => false,
            'stock_actual' => 25,
            'stock_minimo' => 5,
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        $this->insumo2 = Insumo::create([
            'codigo_barras' => 'INS-PATCHCORD-3M',
            'nombre' => 'Patchcord Cat6 3 Metros',
            'unidad' => 'unidad',
            'es_retornable' => true,
            'stock_actual' => 4,
            'stock_minimo' => 10,
            'ubicacion_id' => $this->ubicacion->id,
        ]);
    }

    public function test_buscador_reactivo_filtra_equipos_e_insumos(): void
    {
        $this->actingAs($this->panolero);

        $component = Volt::test('pages.etiquetas.index')
            ->assertSee('Switch Cisco Catalyst 2960')
            ->assertSee('Router MikroTik CCR1009')
            ->assertSee('Conectores RJ45 Cat6 Panduit')
            ->assertSee('Patchcord Cat6 3 Metros');

        // Filtrar por término específico en equipos
        $component->set('busquedaEquipo', 'MikroTik')
            ->assertSee('Router MikroTik CCR1009')
            ->assertDontSee('Switch Cisco Catalyst 2960');

        // Filtrar por término específico en insumos
        $component->set('busquedaInsumo', 'Panduit')
            ->assertSee('Conectores RJ45 Cat6 Panduit')
            ->assertDontSee('Patchcord Cat6 3 Metros');
    }

    public function test_activos_sin_codigo_de_barras_son_excluidos_de_la_seleccion(): void
    {
        $this->actingAs($this->panolero);

        $equipoSinCodigo = Equipo::create([
            'codigo_barras' => '',
            'nombre' => 'Servidor Dell Sin Etiquetar',
            'marca' => 'Dell',
            'modelo' => 'R740',
            'numero_serie' => 'SN-DELL-R740',
            'estado' => 'disponible',
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        $insumoSinCodigo = Insumo::create([
            'codigo_barras' => '',
            'nombre' => 'Pasta Térmica Sin Código',
            'unidad' => 'jeringa',
            'stock_actual' => 10,
            'stock_minimo' => 2,
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        Volt::test('pages.etiquetas.index')
            ->assertDontSee('Servidor Dell Sin Etiquetar')
            ->assertDontSee('Pasta Térmica Sin Código');
    }

    public function test_lectura_optica_despliega_ficha_tecnica_completa_de_equipo_cp12(): void
    {
        $this->actingAs($this->panolero);

        Volt::test('pages.etiquetas.index')
            ->set('codigoLectura', 'EQ-CISCO-SWITCH-01')
            ->call('leerCodigoBarras')
            ->assertHasNoErrors()
            ->assertSee('Equipo Encontrado')
            ->assertSee('Switch Cisco Catalyst 2960')
            ->assertSee('EQ-CISCO-SWITCH-01')
            ->assertSee('Laboratorio Redes A-101')
            ->assertSee('Ingeniería en Informática')
            ->assertSee('SN-CSCO-2960X')
            ->assertSee('Conectividad y Redes')
            ->assertSee('Disponible')
            ->assertSee('Imprimir Etiqueta');
    }

    public function test_lectura_optica_despliega_stock_completo_de_insumo_cp12(): void
    {
        $this->actingAs($this->panolero);

        Volt::test('pages.etiquetas.index')
            ->set('codigoLectura', 'INS-PATCHCORD-3M')
            ->call('leerCodigoBarras')
            ->assertHasNoErrors()
            ->assertSee('Insumo Encontrado')
            ->assertSee('Patchcord Cat6 3 Metros')
            ->assertSee('INS-PATCHCORD-3M')
            ->assertSee((string) $this->insumo2->stock_actual)
            ->assertSee((string) $this->insumo2->stock_minimo)
            ->assertSee('Herramienta Retornable')
            ->assertSee('Stock Crítico');
    }

    public function test_imprimir_etiqueta_individual_desde_resultado_de_escaneo(): void
    {
        $this->actingAs($this->panolero);

        $component = Volt::test('pages.etiquetas.index')
            ->call('imprimirEtiquetaIndividual', 'equipo', $this->equipo1->id)
            ->assertHasNoErrors()
            ->assertSee('EQ-CISCO-SWITCH-01')
            ->assertSee('Switch Cisco Catalyst 2960')
            ->assertSee('Ingeniería en Informática')
            ->assertSee('Lote preparado:')
            ->assertSee('etiqueta(s) generada(s)');

        $this->assertEquals(1, $component->get('totalGeneradas'));
        $this->assertNotNull($component->get('htmlLote'));
    }

    public function test_lote_acepta_30_equipos_y_valida_limite_maximo_100_etiquetas(): void
    {
        $this->actingAs($this->panolero);

        $idsEquipos30 = [];
        for ($i = 1; $i <= 30; $i++) {
            $eq = Equipo::create([
                'codigo_barras' => sprintf('EQ-TAB-LNV-M11-%02d', $i),
                'nombre' => 'Tablet Lenovo Tab M11',
                'marca' => 'Lenovo',
                'modelo' => 'Tab M11',
                'numero_serie' => sprintf('HA19X%02d', $i),
                'estado' => 'disponible',
                'ubicacion_id' => $this->ubicacion->id,
            ]);
            $idsEquipos30[] = $eq->id;
        }

        // Lote de 30 etiquetas debe generarse sin error
        Volt::test('pages.etiquetas.index')
            ->set('seleccionadosEquipos', $idsEquipos30)
            ->call('generarLote')
            ->assertHasNoErrors()
            ->assertSet('totalGeneradas', 30);

        // 101 equipos excede el nuevo límite de 100
        $idsEquipos101 = $idsEquipos30;
        for ($i = 31; $i <= 101; $i++) {
            $eq = Equipo::create([
                'codigo_barras' => sprintf('EQ-TEST-LIMIT-%03d', $i),
                'nombre' => "Equipo Test Límite #{$i}",
                'marca' => 'Marca Test',
                'modelo' => 'Modelo Test',
                'numero_serie' => sprintf('SN-TEST-%03d', $i),
                'estado' => 'disponible',
                'ubicacion_id' => $this->ubicacion->id,
            ]);
            $idsEquipos101[] = $eq->id;
        }

        Volt::test('pages.etiquetas.index')
            ->set('seleccionadosEquipos', $idsEquipos101)
            ->call('generarLote')
            ->assertHasErrors(['lote'])
            ->assertSee('El tamaño máximo permitido por tirada es de 100 etiquetas');
    }

    public function test_filtros_en_cascada_checkbox_maestro_y_solo_pendientes(): void
    {
        $this->actingAs($this->panolero);

        $catComputo = Categoria::create([
            'nombre' => 'Cómputo Móvil',
            'slug' => 'computo-movil',
        ]);
        $subTablets = Subcategoria::create([
            'categoria_id' => $catComputo->id,
            'nombre' => 'Tablets',
            'slug' => 'tablets',
            'codigo_prefijo' => 'EQ-TAB',
            'orden' => 1,
        ]);

        $tab1 = Equipo::create([
            'codigo_barras' => 'EQ-TAB-LNV-M11-01',
            'nombre' => 'Tablet Lenovo Tab M11',
            'marca' => 'Lenovo',
            'modelo' => 'Tab M11',
            'numero_serie' => 'HA19X01',
            'estado' => 'disponible',
            'categoria_id' => $catComputo->id,
            'subcategoria_id' => $subTablets->id,
            'etiqueta_impresa' => false,
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        $tab2 = Equipo::create([
            'codigo_barras' => 'EQ-TAB-LNV-M11-02',
            'nombre' => 'Tablet Lenovo Tab M11',
            'marca' => 'Lenovo',
            'modelo' => 'Tab M11',
            'numero_serie' => 'HA19X02',
            'estado' => 'disponible',
            'categoria_id' => $catComputo->id,
            'subcategoria_id' => $subTablets->id,
            'etiqueta_impresa' => true,
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        // Cascada Categoría -> Subcategoría -> Familia/Modelo + Solo pendientes
        $component = Volt::test('pages.etiquetas.index')
            ->set('filtroCategoriaId', (string) $catComputo->id)
            ->set('filtroSubcategoriaId', (string) $subTablets->id)
            ->assertSee('EQ-TAB-LNV-M11 · Tablet Lenovo Tab M11')
            ->set('filtroFamilia', 'Tablet Lenovo Tab M11||Tab M11')
            ->assertSee('EQ-TAB-LNV-M11-01')
            ->assertSee('EQ-TAB-LNV-M11-02')
            ->assertDontSee('Switch Cisco Catalyst 2960');

        // Solo pendientes oculta tab2
        $component->set('soloPendientes', true)
            ->assertSee('EQ-TAB-LNV-M11-01')
            ->assertDontSee('EQ-TAB-LNV-M11-02');

        // Checkbox maestro selecciona todas las pendientes visibles y al imprimir marca etiqueta_impresa = true
        $component->call('toggleSeleccionarTodosVisibles')
            ->assertSet('seleccionadosEquipos', [(string) $tab1->id])
            ->call('generarLote')
            ->assertSet('totalGeneradas', 1)
            ->call('imprimirLote')
            ->assertDispatched('imprimir-etiquetas-lote');

        $this->assertTrue($tab1->fresh()->etiqueta_impresa);
        $this->assertNotNull($tab1->fresh()->etiqueta_impresa_en);
    }

    public function test_insumos_permiten_multiples_copias_y_validan_total_combinado(): void
    {
        $this->actingAs($this->panolero);

        $component = Volt::test('pages.etiquetas.index')
            ->set('seleccionadosInsumos', [$this->insumo1->id])
            ->set("copiasInsumos.{$this->insumo1->id}", 10)
            ->call('generarLote')
            ->assertHasNoErrors()
            ->assertSet('totalGeneradas', 10);

        $this->assertCount(10, $component->get('itemsLote'));
    }

    public function test_busqueda_y_reimpresion_por_numero_de_serie_y_exclusion_dados_de_baja(): void
    {
        $this->actingAs($this->panolero);

        $tablet = Equipo::create([
            'codigo_barras' => 'EQ-TAB-LNV-M11-04',
            'nombre' => 'Tablet Lenovo Tab M11',
            'marca' => 'Lenovo',
            'modelo' => 'Tab M11',
            'numero_serie' => 'HA19X04',
            'estado' => 'disponible',
            'etiqueta_impresa' => true,
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        Equipo::create([
            'codigo_barras' => 'EQ-BAJA-01',
            'nombre' => 'Equipo Dado de Baja',
            'marca' => 'HP',
            'modelo' => 'Old',
            'numero_serie' => 'SN-BAJA-99',
            'estado' => 'dado_de_baja',
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        // Dado de baja excluido del listado normal (M3), búsqueda por S/N en caja izquierda y en escáner superior (M2)
        Volt::test('pages.etiquetas.index')
            ->assertDontSee('Equipo Dado de Baja')
            ->set('busquedaEquipo', 'HA19X04')
            ->assertSee('EQ-TAB-LNV-M11-04')
            ->assertDontSee('Switch Cisco Catalyst 2960')
            ->set('codigoLectura', 'HA19X04')
            ->call('leerCodigoBarras')
            ->assertHasNoErrors()
            ->assertSee('Recuperado por N° Serie')
            ->assertSee('EQ-TAB-LNV-M11-04');
    }

    public function test_descarga_lote_en_formato_txt(): void
    {
        $this->actingAs($this->panolero);

        $component = Volt::test('pages.etiquetas.index')
            ->set('seleccionadosEquipos', [$this->equipo1->id])
            ->set('seleccionadosInsumos', [$this->insumo1->id])
            ->call('generarLote')
            ->assertHasNoErrors();

        // Llamar a descargarTxtLote
        $component->call('descargarTxtLote')
            ->assertFileDownloaded();
    }
}
