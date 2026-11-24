<?php

namespace Tests\Feature\Panol;

use App\Models\Carrera;
use App\Models\Categoria;
use App\Models\Equipo;
use App\Models\Insumo;
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

    public function test_validacion_limite_maximo_20_etiquetas_sla_07(): void
    {
        $this->actingAs($this->panolero);

        // Crear 21 equipos para intentar un lote excedido
        $idsEquipos = [];
        for ($i = 1; $i <= 21; $i++) {
            $eq = Equipo::create([
                'codigo_barras' => sprintf('EQ-TEST-LIMIT-%03d', $i),
                'nombre' => "Equipo Test Límite #{$i}",
                'marca' => 'Marca Test',
                'modelo' => 'Modelo Test',
                'numero_serie' => sprintf('SN-TEST-%03d', $i),
                'estado' => 'disponible',
                'ubicacion_id' => $this->ubicacion->id,
            ]);
            $idsEquipos[] = $eq->id;
        }

        Volt::test('pages.etiquetas.index')
            ->set('seleccionadosEquipos', $idsEquipos)
            ->call('generarLote')
            ->assertHasErrors(['lote'])
            ->assertSee('El tamaño máximo institucional por tirada es de 20 etiquetas (SLA-07)');
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
