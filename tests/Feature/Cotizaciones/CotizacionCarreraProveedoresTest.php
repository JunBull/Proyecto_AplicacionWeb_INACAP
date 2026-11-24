<?php

namespace Tests\Feature\Cotizaciones;

use App\Jobs\DespacharCotizacionJob;
use App\Models\Carrera;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\ProveedorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CotizacionCarreraProveedoresTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Carrera $carreraInformatica;

    protected Carrera $carreraTelecom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email' => 'admin.cotiz@inacap.cl',
            'rol' => 'administrador',
            'activo' => true,
        ]);

        $this->carreraInformatica = Carrera::create([
            'codigo' => 'INF-2026',
            'nombre' => 'Ingeniería en Informática',
            'activa' => true,
        ]);

        $this->carreraTelecom = Carrera::create([
            'codigo' => 'TEL-2026',
            'nombre' => 'Telecomunicaciones y Redes',
            'activa' => true,
        ]);
    }

    public function test_proveedor_se_asocia_a_carrera_y_filtro_funciona(): void
    {
        $service = app(ProveedorService::class);

        $provInfo = $service->registrarProveedor([
            'razon_social' => 'Servicios Computacionales Austral SpA',
            'rut' => '76.888.999-1',
            'correo' => 'ventas@computacionaustral.cl',
            'telefono' => '+56 9 1111 2222',
            'tipo' => 'insumos',
            'activo' => true,
            'carreras_ids' => [$this->carreraInformatica->id],
        ]);

        $provTelecom = $service->registrarProveedor([
            'razon_social' => 'Fibra y Antenas Temuco Ltda',
            'rut' => '77.333.444-2',
            'correo' => 'contacto@antenas.cl',
            'telefono' => '+56 9 3333 4444',
            'tipo' => 'insumos',
            'activo' => true,
            'carreras_ids' => [$this->carreraTelecom->id],
        ]);

        $provGeneral = $service->registrarProveedor([
            'razon_social' => 'Librería e Insumos Globales',
            'rut' => '78.555.666-3',
            'correo' => 'ventas@globales.cl',
            'tipo' => 'insumos',
            'activo' => true,
            'carreras_ids' => [], // General
        ]);

        // Consulta para carrera de Informática
        $paraInformatica = Proveedor::paraCarrera($this->carreraInformatica->id)->pluck('id')->all();
        $this->assertContains($provInfo->id, $paraInformatica);
        $this->assertContains($provGeneral->id, $paraInformatica);
        $this->assertNotContains($provTelecom->id, $paraInformatica);

        // Consulta para carrera de Telecomunicaciones
        $paraTelecom = Proveedor::paraCarrera($this->carreraTelecom->id)->pluck('id')->all();
        $this->assertContains($provTelecom->id, $paraTelecom);
        $this->assertContains($provGeneral->id, $paraTelecom);
        $this->assertNotContains($provInfo->id, $paraTelecom);
    }

    public function test_componente_cotizaciones_precarga_proveedores_de_carrera_seleccionada(): void
    {
        $service = app(ProveedorService::class);

        $provInfo = $service->registrarProveedor([
            'razon_social' => 'Hardware y Servidores SpA',
            'rut' => '76.111.333-5',
            'correo' => 'contacto@hwservidores.cl',
            'tipo' => 'insumos',
            'activo' => true,
            'carreras_ids' => [$this->carreraInformatica->id],
        ]);

        $provTelecom = $service->registrarProveedor([
            'razon_social' => 'Redes Ópticas SpA',
            'rut' => '77.222.444-6',
            'correo' => 'contacto@redesopticas.cl',
            'tipo' => 'insumos',
            'activo' => true,
            'carreras_ids' => [$this->carreraTelecom->id],
        ]);

        $this->actingAs($this->admin);

        // Al iniciar con Informática, debe precargar provInfo pero no provTelecom
        $component = Volt::test('pages.cotizaciones.index')
            ->set('carrera_id', $this->carreraInformatica->id);

        $component->call('precargarProveedoresPorCarrera');

        $seleccionados = $component->get('proveedoresSeleccionados');
        $this->assertContains((string) $provInfo->id, $seleccionados);
        $this->assertNotContains((string) $provTelecom->id, $seleccionados);

        // Al cambiar a Telecomunicaciones, debe actualizar y precargar provTelecom
        $component->set('carrera_id', $this->carreraTelecom->id);
        $component->call('precargarProveedoresPorCarrera');

        $seleccionadosTelecom = $component->get('proveedoresSeleccionados');
        $this->assertContains((string) $provTelecom->id, $seleccionadosTelecom);
        $this->assertNotContains((string) $provInfo->id, $seleccionadosTelecom);
    }

    public function test_despacho_masivo_a_proveedores_precargados(): void
    {
        Queue::fake();

        $service = app(ProveedorService::class);
        $prov = $service->registrarProveedor([
            'razon_social' => 'Mayorista de Cables y Conectores',
            'rut' => '76.444.888-9',
            'correo' => 'ventas@cables.cl',
            'tipo' => 'insumos',
            'activo' => true,
            'carreras_ids' => [$this->carreraInformatica->id],
        ]);

        $this->actingAs($this->admin);

        Volt::test('pages.cotizaciones.index')
            ->set('carrera_id', $this->carreraInformatica->id)
            ->set('descripcion', 'Requerimiento de 10 Patch Cords Cat6 y 2 Bobinas UTP')
            ->call('precargarProveedoresPorCarrera')
            ->call('guardarSolicitud');

        $this->assertDatabaseHas('solicitudes_cotizacion', [
            'carrera_id' => $this->carreraInformatica->id,
            'descripcion' => 'Requerimiento de 10 Patch Cords Cat6 y 2 Bobinas UTP',
            'estado' => 'enviada',
        ]);

        $this->assertDatabaseHas('envios_cotizacion', [
            'proveedor_id' => $prov->id,
            'estado' => 'pendiente',
        ]);

        Queue::assertPushed(DespacharCotizacionJob::class);
    }
}
