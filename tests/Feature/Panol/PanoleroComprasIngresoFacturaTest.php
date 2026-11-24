<?php

namespace Tests\Feature\Panol;

use App\Models\Carrera;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\Insumo;
use App\Models\Proveedor;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\PresupuestoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PanoleroComprasIngresoFacturaTest extends TestCase
{
    use RefreshDatabase;

    private User $panolero;

    private User $admin;

    private Carrera $carrera;

    private Ubicacion $ubicacion;

    private Proveedor $proveedor;

    private Insumo $insumo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->panolero = User::factory()->create([
            'rol' => 'encargado_panol',
            'activo' => true,
        ]);

        $this->admin = User::factory()->create([
            'rol' => 'administrador',
            'activo' => true,
        ]);

        $this->carrera = Carrera::create([
            'codigo' => 'INF-ING',
            'nombre' => 'Ingeniería en Informática',
            'activa' => true,
        ]);

        $this->ubicacion = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Pañol Central Informática',
            'tipo' => 'panol',
        ]);

        $this->proveedor = Proveedor::create([
            'rut' => '76.123.456-7',
            'razon_social' => 'Conectividad y Redes Chile SpA',
            'correo' => 'ventas@redeschile.cl',
            'tipo' => 'insumos',
            'activo' => true,
        ]);

        $this->insumo = Insumo::create([
            'codigo_barras' => 'INS-RJ45-TEST',
            'nombre' => 'Conector RJ-45 Cat6',
            'unidad' => 'unidad',
            'es_retornable' => false,
            'stock_actual' => 20,
            'stock_minimo' => 10,
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        // Presupuesto 2026: $1.000.000
        app(PresupuestoService::class)->obtenerOCrearPresupuesto($this->carrera, 2026, 1000000);
    }

    public function test_vista_muestra_textos_simplificados_sin_jargon_academico(): void
    {
        $this->actingAs($this->admin);

        Volt::test('pages.compras.index')
            ->assertSee('Ingreso por Factura')
            ->assertSee('+ Registrar')
            ->assertDontSee('Proceso 2: Recepción e Ingreso Documental con OCR')
            ->assertDontSee('+ Registrar Ingreso por Factura / OC')
            ->call('abrirModalIngreso')
            ->assertSet('mostrarModalIngreso', true)
            ->assertSet('pasoActual', 1)
            ->assertSee('Cargar Factura')
            ->assertSee('Saltar y digitar manualmente')
            ->assertSee('Enviar')
            ->assertDontSee('Paso 1: Carga y Reconocimiento Documental (OCR)')
            ->assertDontSee('Regla BPMN Proceso 2')
            ->assertDontSee('Procesar con Motor OCR');
    }

    public function test_paso_dos_muestra_llenado_manual_y_etiquetas_simplificadas(): void
    {
        $this->actingAs($this->admin);

        Volt::test('pages.compras.index')
            ->call('abrirModalIngreso')
            ->set('pasoActual', 2)
            ->assertSee('Llenado Manual')
            ->assertSee('Carrera *')
            ->assertSee('Importe Total:')
            ->assertSee('← Volver')
            ->assertSee('Confirmar')
            ->assertDontSee('Paso 2: Revisión, Corrección y Confirmación Manual')
            ->assertDontSee('Carrera (Presupuesto) *')
            ->assertDontSee('Total Compra a Debitar:')
            ->assertDontSee('← Volver al OCR')
            ->assertDontSee('Confirmar Ingreso al Inventario y Descontar Presupuesto')
            ->call('$set', 'pasoActual', 1)
            ->assertSee('Cargar Factura');
    }

    public function test_confirmar_valida_campos_requeridos_en_paso_dos(): void
    {
        $this->actingAs($this->admin);

        Volt::test('pages.compras.index')
            ->call('abrirModalIngreso')
            ->set('pasoActual', 2)
            ->set('proveedor_id', null)
            ->set('numero_documento', '')
            ->set('items', [
                ['insumo_id' => null, 'descripcion' => '', 'cantidad' => 0, 'costo_unitario' => -10, 'impuesto' => 0],
            ])
            ->call('confirmarIngresoInventario')
            ->assertHasErrors(['proveedor_id', 'numero_documento', 'items.0.descripcion', 'items.0.cantidad', 'items.0.costo_unitario'])
            ->assertSet('mostrarModalIngreso', true);
    }

    public function test_boton_confirmar_persiste_compra_actualiza_stock_y_descuenta_presupuesto(): void
    {
        $this->actingAs($this->admin);

        Volt::test('pages.compras.index')
            ->call('abrirModalIngreso')
            ->set('pasoActual', 2)
            ->set('proveedor_id', $this->proveedor->id)
            ->set('carrera_id', $this->carrera->id)
            ->set('numero_documento', 'FAC-78901')
            ->set('fecha', '2026-11-09')
            ->set('items', [
                [
                    'insumo_id' => $this->insumo->id,
                    'descripcion' => 'Conector RJ-45 Cat6 bolsa 50 unidades',
                    'cantidad' => 50,
                    'costo_unitario' => 1000,
                    'impuesto' => 0,
                ],
            ])
            ->call('confirmarIngresoInventario')
            ->assertHasNoErrors()
            ->assertSet('mostrarModalIngreso', false)
            ->assertSee('Compra confirmada, presupuesto debitado e inventario actualizado exitosamente');

        // Verificar persistencia de Compra
        $this->assertDatabaseHas('compras', [
            'numero_documento' => 'FAC-78901',
            'proveedor_id' => $this->proveedor->id,
            'carrera_id' => $this->carrera->id,
            'monto_total' => 50000,
            'estado' => 'ingresada_inventario',
        ]);

        $compra = Compra::where('numero_documento', 'FAC-78901')->first();
        $this->assertNotNull($compra);

        // Verificar detalle de compra
        $this->assertDatabaseHas('compra_detalles', [
            'compra_id' => $compra->id,
            'insumo_id' => $this->insumo->id,
            'cantidad' => 50,
            'costo_unitario' => 1000,
        ]);

        // Verificar actualización atómica de stock en insumo (20 inicial + 50 = 70)
        $this->assertEquals(70, (float) $this->insumo->fresh()->stock_actual);

        // Verificar registro de Kardex
        $this->assertDatabaseHas('movimientos_inventario', [
            'insumo_id' => $this->insumo->id,
            'compra_id' => $compra->id,
            'tipo' => 'ingreso_compra',
            'cantidad' => 50,
        ]);

        // Verificar descuento presupuestario (1.000.000 - 50.000 = 950.000)
        $this->assertEquals(950000, (float) app(PresupuestoService::class)->consultarSaldo($this->carrera, 2026));

        // Verificar movimiento en presupuesto
        $this->assertDatabaseHas('movimientos_presupuesto', [
            'compra_id' => $compra->id,
            'tipo' => 'cargo',
            'monto' => 50000,
        ]);
    }

    public function test_menu_navegacion_muestra_ingreso_por_factura(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('admin.compras'));
        $response->assertStatus(200);
        $response->assertSee('Ingreso por Factura');
        $response->assertDontSee('Compras y OCR Documental');
    }

    public function test_ingreso_manual_permite_ver_items_ingresados_en_tabla_y_modal(): void
    {
        $this->actingAs($this->admin);

        $compraManual = Compra::create([
            'proveedor_id' => $this->proveedor->id,
            'carrera_id' => $this->carrera->id,
            'numero_documento' => 'FAC-MANUAL-123',
            'fecha' => '2026-11-12',
            'monto_total' => 25000,
            'estado' => 'ingresada_inventario',
        ]);

        CompraDetalle::create([
            'compra_id' => $compraManual->id,
            'insumo_id' => $this->insumo->id,
            'descripcion' => 'Pack Conectores RJ45 Dorados',
            'cantidad' => 25,
            'costo_unitario' => 1000,
            'impuesto' => 0,
        ]);

        Volt::test('pages.compras.index')
            ->assertSee('FAC-MANUAL-123')
            ->assertSee('Pack Conectores RJ45 Dorados')
            ->assertSee('1 ítem(s)')
            ->assertSee('Sin archivo')
            ->assertSee('Ver ítems')
            ->call('verDetalle', $compraManual->id)
            ->assertSet('mostrarModalDetalle', true)
            ->assertSet('compraDetalleId', $compraManual->id)
            ->assertSee('Detalle de Factura / Compra')
            ->assertSee('FAC-MANUAL-123')
            ->assertSee('Ingreso Manual')
            ->assertSee('Pack Conectores RJ45 Dorados')
            ->assertSee('Conector RJ-45 Cat6')
            ->assertSee('$25.000')
            ->call('cerrarModalDetalle')
            ->assertSet('mostrarModalDetalle', false);
    }

    public function test_panolero_no_puede_acceder_a_ingreso_por_factura(): void
    {
        $this->actingAs($this->panolero);

        $response = $this->get(route('admin.compras'));
        $response->assertStatus(403);
    }
}
