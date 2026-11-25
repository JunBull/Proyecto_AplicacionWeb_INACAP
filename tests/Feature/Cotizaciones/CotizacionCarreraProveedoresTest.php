<?php

namespace Tests\Feature\Cotizaciones;

use App\Jobs\DespacharCotizacionJob;
use App\Models\Carrera;
use App\Models\Cotizacion;
use App\Models\EnvioCotizacion;
use App\Models\Proveedor;
use App\Models\SolicitudCotizacion;
use App\Models\User;
use App\Services\CotizacionService;
use App\Services\ProveedorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
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

    public function test_modal_nueva_solicitud_solo_lista_proveedores_de_insumos_y_ambos_excluyendo_mantenimiento(): void
    {
        $service = app(ProveedorService::class);

        $provInsumos = $service->registrarProveedor([
            'razon_social' => 'Insumos y Redes Austral SpA',
            'rut' => '76.101.101-1',
            'correo' => 'insumos@austral.cl',
            'tipo' => 'insumos',
            'activo' => true,
            'carreras_ids' => [$this->carreraInformatica->id],
        ]);

        $provAmbos = $service->registrarProveedor([
            'razon_social' => 'Comercializadora Integral SpA',
            'rut' => '76.102.102-2',
            'correo' => 'integral@comercial.cl',
            'tipo' => 'ambos',
            'activo' => true,
            'carreras_ids' => [],
        ]);

        $provMantCarrera = $service->registrarProveedor([
            'razon_social' => 'Servicio Tecnico Carrera SpA',
            'rut' => '76.103.103-3',
            'correo' => 'mantcarrera@st.cl',
            'tipo' => 'mantenimiento',
            'activo' => true,
            'carreras_ids' => [$this->carreraInformatica->id],
        ]);

        $provMantGeneral = $service->registrarProveedor([
            'razon_social' => 'Soporte General Mantencion Ltda',
            'rut' => '76.104.104-4',
            'correo' => 'mantgeneral@st.cl',
            'tipo' => 'mantenimiento',
            'activo' => true,
            'carreras_ids' => [],
        ]);

        $this->actingAs($this->admin);

        $component = Volt::test('pages.cotizaciones.index')
            ->set('carrera_id', $this->carreraInformatica->id)
            ->call('abrirModalSolicitud')
            ->assertSet('mostrarModalSolicitud', true)
            ->assertSee('Insumos y Redes Austral SpA')
            ->assertSee('Comercializadora Integral SpA')
            ->assertSee('Ambos rubros')
            ->assertDontSee('Servicio Tecnico Carrera SpA')
            ->assertDontSee('Soporte General Mantencion Ltda');

        $seleccionados = $component->get('proveedoresSeleccionados');
        $this->assertContains((string) $provInsumos->id, $seleccionados);
        $this->assertContains((string) $provAmbos->id, $seleccionados);
        $this->assertNotContains((string) $provMantCarrera->id, $seleccionados);
        $this->assertNotContains((string) $provMantGeneral->id, $seleccionados);
    }

    public function test_precarga_queda_vacia_si_carrera_solo_tiene_proveedores_de_mantenimiento(): void
    {
        $service = app(ProveedorService::class);

        $service->registrarProveedor([
            'razon_social' => 'Mantencion Exclusiva Inf Ltda',
            'rut' => '76.201.201-1',
            'correo' => 'mant@exclusiva.cl',
            'tipo' => 'mantenimiento',
            'activo' => true,
            'carreras_ids' => [$this->carreraInformatica->id],
        ]);

        $this->actingAs($this->admin);

        $component = Volt::test('pages.cotizaciones.index')
            ->set('carrera_id', $this->carreraInformatica->id)
            ->call('abrirModalSolicitud')
            ->assertSet('proveedoresSeleccionados', [])
            ->assertDontSee('Mantencion Exclusiva Inf Ltda')
            ->assertSee('No hay proveedores activos de insumos (o ambos rubros)');
    }

    public function test_modal_cargar_cotizacion_solo_lista_proveedores_notificados_en_la_solicitud(): void
    {
        $service = app(ProveedorService::class);

        $provEnviado = $service->registrarProveedor([
            'razon_social' => 'Proveedor Notificado Enviado SpA',
            'rut' => '76.301.301-1',
            'correo' => 'enviado@prov.cl',
            'tipo' => 'insumos',
            'activo' => true,
        ]);

        $provFallido = $service->registrarProveedor([
            'razon_social' => 'Proveedor Notificado Fallido Ltda',
            'rut' => '76.302.302-2',
            'correo' => 'fallido@prov.cl',
            'tipo' => 'ambos',
            'activo' => true,
        ]);

        $service->registrarProveedor([
            'razon_social' => 'Proveedor No Invitado SpA',
            'rut' => '76.303.303-3',
            'correo' => 'noinvitado@prov.cl',
            'tipo' => 'insumos',
            'activo' => true,
        ]);

        $solicitud = SolicitudCotizacion::create([
            'carrera_id' => $this->carreraInformatica->id,
            'creada_por' => $this->admin->id,
            'descripcion' => 'Conectores RJ45 Cat6',
            'estado' => 'enviada',
            'fecha_creacion' => now(),
        ]);

        EnvioCotizacion::create([
            'solicitud_cotizacion_id' => $solicitud->id,
            'proveedor_id' => $provEnviado->id,
            'estado' => 'enviado',
            'intentos' => 1,
            'enviado_en' => now(),
        ]);

        EnvioCotizacion::create([
            'solicitud_cotizacion_id' => $solicitud->id,
            'proveedor_id' => $provFallido->id,
            'estado' => 'fallido',
            'intentos' => 1,
            'error' => 'SMTP timeout',
        ]);

        Cotizacion::create([
            'solicitud_cotizacion_id' => $solicitud->id,
            'proveedor_id' => $provEnviado->id,
            'monto_total' => 120000,
            'fecha_recepcion' => now()->toDateString(),
        ]);

        $this->actingAs($this->admin);

        Volt::test('pages.cotizaciones.index')
            ->call('abrirCargaCotizacion', $solicitud->id)
            ->assertSet('mostrarModalCargarCotizacion', true)
            ->assertSee('Proveedor Notificado Enviado SpA (RUT: 76.301.301-1) — (Ya cotizó)')
            ->assertSee('Proveedor Notificado Fallido Ltda (RUT: 76.302.302-2)')
            ->assertDontSee('Proveedor No Invitado SpA');
    }

    public function test_boton_cargar_cotizacion_no_se_muestra_en_solicitud_borrador_sin_envios(): void
    {
        SolicitudCotizacion::create([
            'carrera_id' => $this->carreraInformatica->id,
            'creada_por' => $this->admin->id,
            'descripcion' => 'Solicitud borrador sin envios',
            'estado' => 'borrador',
            'fecha_creacion' => now(),
        ]);

        $this->actingAs($this->admin);

        Volt::test('pages.cotizaciones.index')
            ->assertSee('Solicitud borrador sin envios')
            ->assertDontSee('+ Cargar Cotización');
    }

    public function test_backend_rechaza_despacho_mantenimiento_y_registro_de_proveedor_no_notificado(): void
    {
        Queue::fake();

        $proveedorService = app(ProveedorService::class);
        $cotizacionService = app(CotizacionService::class);

        $provInsumos = $proveedorService->registrarProveedor([
            'razon_social' => 'Insumos Permitido SpA',
            'rut' => '76.401.401-1',
            'correo' => 'ok@insumos.cl',
            'tipo' => 'insumos',
            'activo' => true,
        ]);

        $provNoNotificado = $proveedorService->registrarProveedor([
            'razon_social' => 'Insumos No Notificado SpA',
            'rut' => '76.402.402-2',
            'correo' => 'otro@insumos.cl',
            'tipo' => 'insumos',
            'activo' => true,
        ]);

        $provMantenimiento = $proveedorService->registrarProveedor([
            'razon_social' => 'Mantenimiento Prohibido SpA',
            'rut' => '76.403.403-3',
            'correo' => 'mant@prohibido.cl',
            'tipo' => 'mantenimiento',
            'activo' => true,
        ]);

        $solicitud = $cotizacionService->crearSolicitudCotizacion([
            'carrera_id' => $this->carreraInformatica->id,
            'descripcion' => 'Compra de switches de acceso',
        ], $this->admin);

        // Rechazar registrar cotización sobre solicitud en borrador
        try {
            $cotizacionService->registrarCotizacion($solicitud, $provInsumos, 100000, now()->toDateString());
            $this->fail('Debió rechazar registrar cotización en solicitud borrador.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('proveedorCotizacionId', $e->errors());
        }

        // Rechazar despacho si incluye proveedor de mantenimiento
        try {
            $cotizacionService->despacharSolicitudProveedores($solicitud, [$provInsumos->id, $provMantenimiento->id]);
            $this->fail('Debió rechazar despacho a proveedor de mantenimiento.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('proveedores', $e->errors());
        }

        // Despachar solo a provInsumos funciona
        $cotizacionService->despacharSolicitudProveedores($solicitud, [$provInsumos->id]);

        // Rechazar registrar cotización de proveedor de mantenimiento
        try {
            $cotizacionService->registrarCotizacion($solicitud->fresh(), $provMantenimiento, 150000, now()->toDateString());
            $this->fail('Debió rechazar cotización de proveedor de mantenimiento.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('proveedorCotizacionId', $e->errors());
        }

        // Rechazar registrar cotización de proveedor de insumos que no fue notificado en esta solicitud
        try {
            $cotizacionService->registrarCotizacion($solicitud->fresh(), $provNoNotificado, 150000, now()->toDateString());
            $this->fail('Debió rechazar cotización de proveedor no notificado.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('proveedorCotizacionId', $e->errors());
        }
    }
}
