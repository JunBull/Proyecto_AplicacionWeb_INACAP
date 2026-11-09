<?php

namespace Tests\Feature\Panol;

use App\Models\Carrera;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PanoleroAdquisicionesSimplificadoTest extends TestCase
{
    use RefreshDatabase;

    private User $panolero;

    private Carrera $carrera;

    private Proveedor $proveedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->panolero = User::factory()->create([
            'name' => 'Pañolero Adquisiciones Test',
            'email' => 'panolero.adq@inacap.cl',
            'rol' => 'encargado_panol',
            'activo' => true,
        ]);

        $this->carrera = Carrera::create([
            'codigo' => 'TEL-02',
            'nombre' => 'Telecomunicaciones y Redes',
            'activa' => true,
        ]);

        $this->proveedor = Proveedor::create([
            'rut' => '77.888.999-1',
            'razon_social' => 'Ferretería Industrial del Sur SpA',
            'correo' => 'ventas@ferreteriasur.cl',
            'tipo' => 'insumos',
            'activo' => true,
        ]);
    }

    public function test_vista_proveedores_muestra_titulos_limpios_sin_codigos_auditoria(): void
    {
        $this->actingAs($this->panolero);

        $component = Volt::test('pages.proveedores.index');

        $component->assertSee('Proveedores');
        $component->assertSee('Contactos comerciales y rubros de proveedores registrados.');

        $component->assertDontSee('Directorio de Proveedores Categorizados');
        $component->assertDontSee('(CP-16)');
    }

    public function test_vista_cotizaciones_muestra_titulos_limpios_sin_codigos_auditoria(): void
    {
        $this->actingAs($this->panolero);

        $component = Volt::test('pages.cotizaciones.index');

        $component->assertSee('Solicitudes de Cotización');
        $component->assertSee('Emisión de cotizaciones a proveedores y adjudicación de compras.');

        $component->assertDontSee('Proceso 1: Cotizaciones y Adjudicaciones');
        $component->assertDontSee('(CP-17, CP-18, KPI-08, SLA-05)');
        $component->assertDontSee('(SLA-05 / KPI-10)');
    }
}
