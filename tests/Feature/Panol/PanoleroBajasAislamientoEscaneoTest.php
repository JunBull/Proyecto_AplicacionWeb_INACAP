<?php

namespace Tests\Feature\Panol;

use App\Models\BajaReemplazo;
use App\Models\Carrera;
use App\Models\Equipo;
use App\Models\Ubicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PanoleroBajasAislamientoEscaneoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $panolero1;

    private User $panolero2;

    private Carrera $carrera;

    private Ubicacion $ubicacion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email' => 'admin.bajas@inacap.cl',
            'rol' => 'administrador',
            'activo' => true,
        ]);

        $this->panolero1 = User::factory()->create([
            'email' => 'pedro.panolero@inacap.cl',
            'nombre' => 'Pedro Pañolero',
            'rol' => 'encargado_panol',
            'activo' => true,
        ]);

        $this->panolero2 = User::factory()->create([
            'email' => 'juan.panolero@inacap.cl',
            'nombre' => 'Juan Pañolero',
            'rol' => 'encargado_panol',
            'activo' => true,
        ]);

        $this->carrera = Carrera::create([
            'codigo' => 'INF-RED',
            'nombre' => 'Telecomunicaciones y Redes',
            'activa' => true,
        ]);

        $this->ubicacion = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Pañol Central de Telecomunicaciones',
            'codigo' => 'PAN-TEL-01',
            'tipo' => 'laboratorio',
        ]);
    }

    public function test_administrador_visualiza_todas_las_propuestas_en_la_tabla(): void
    {
        $equipo1 = Equipo::create([
            'nombre' => 'Switch Cisco Catalyst 3850',
            'marca' => 'Cisco',
            'modelo' => 'WS-C3850-24T-L',
            'numero_serie' => 'SN-CSC-3850-01',
            'codigo_barras' => 'EQ-CSC-3850-01',
            'ubicacion_id' => $this->ubicacion->id,
            'estado' => 'pendiente_resolucion',
            'criticidad' => 'alta',
        ]);

        $equipo2 = Equipo::create([
            'nombre' => 'Router Juniper SRX300',
            'marca' => 'Juniper',
            'modelo' => 'SRX300',
            'numero_serie' => 'SN-JUN-300-01',
            'codigo_barras' => 'EQ-JUN-300-01',
            'ubicacion_id' => $this->ubicacion->id,
            'estado' => 'dado_de_baja',
            'criticidad' => 'media',
        ]);

        BajaReemplazo::create([
            'equipo_id' => $equipo1->id,
            'tipo' => 'baja',
            'motivo' => 'Cisco Catalyst con daño en ASIC principal.',
            'estado' => 'propuesta',
            'propuesta_por' => $this->panolero1->id,
            'fecha' => now()->toDateString(),
        ]);

        BajaReemplazo::create([
            'equipo_id' => $equipo2->id,
            'tipo' => 'reemplazo',
            'motivo' => 'Router Juniper quemado por sobretensión eléctrica.',
            'estado' => 'aprobada',
            'propuesta_por' => $this->panolero2->id,
            'aprobada_por' => $this->admin->id,
            'fecha' => now()->subDays(5)->toDateString(),
        ]);

        $this->actingAs($this->admin);

        Volt::test('pages.bajas.index')
            ->assertSee('Switch Cisco Catalyst 3850')
            ->assertSee('Router Juniper SRX300')
            ->assertSee('Pedro Pañolero')
            ->assertSee('Juan Pañolero');
    }

    public function test_panolero_solo_visualiza_sus_propias_propuestas_personales(): void
    {
        $equipo1 = Equipo::create([
            'nombre' => 'Switch Cisco Catalyst 2960',
            'marca' => 'Cisco',
            'modelo' => 'WS-C2960',
            'numero_serie' => 'SN-CSC-2960-01',
            'codigo_barras' => 'EQ-CSC-2960-01',
            'ubicacion_id' => $this->ubicacion->id,
            'estado' => 'pendiente_resolucion',
            'criticidad' => 'alta',
        ]);

        $equipo2 = Equipo::create([
            'nombre' => 'Notebook Lenovo ThinkPad T14',
            'marca' => 'Lenovo',
            'modelo' => 'T14 Gen 2',
            'numero_serie' => 'SN-LNV-T14-02',
            'codigo_barras' => 'EQ-LNV-T14-02',
            'ubicacion_id' => $this->ubicacion->id,
            'estado' => 'no_disponible',
            'criticidad' => 'media',
        ]);

        // Propuesta realizada por Pedro Pañolero
        BajaReemplazo::create([
            'equipo_id' => $equipo1->id,
            'tipo' => 'baja',
            'motivo' => 'Sustento técnico: switch fallando en puertos gigabit.',
            'estado' => 'propuesta',
            'propuesta_por' => $this->panolero1->id,
            'fecha' => now()->toDateString(),
        ]);

        // Propuesta realizada por el Administrador u otro usuario
        BajaReemplazo::create([
            'equipo_id' => $equipo2->id,
            'tipo' => 'baja',
            'motivo' => 'Pantalla dañada, costo supera 75% de reposición.',
            'estado' => 'rechazada',
            'propuesta_por' => $this->admin->id,
            'aprobada_por' => $this->admin->id,
            'fecha' => now()->subDays(10)->toDateString(),
        ]);

        // Cuando ingresa Pedro Pañolero
        $this->actingAs($this->panolero1);

        Volt::test('pages.bajas.index')
            ->assertSee('Switch Cisco Catalyst 2960')
            ->assertSee('Sustento técnico: switch fallando')
            ->assertDontSee('Notebook Lenovo ThinkPad T14')
            ->assertDontSee('Pantalla dañada, costo supera');
    }

    public function test_escaneo_codigo_barras_inexistente_muestra_error_y_bloquea_envio(): void
    {
        $this->actingAs($this->panolero1);

        Volt::test('pages.bajas.index')
            ->call('abrirModalProponer')
            ->set('codigoBarras', 'EQ-INEXISTENTE-999')
            ->assertSet('errorEquipo', "El código 'EQ-INEXISTENTE-999' no está registrado en el inventario institucional.")
            ->assertSet('equipo_id', null)
            ->call('guardarPropuesta')
            ->assertHasErrors(['codigoBarras']);
    }

    public function test_escaneo_equipo_dado_de_baja_muestra_error_especifico_y_bloquea_envio(): void
    {
        $equipo = Equipo::create([
            'nombre' => 'Monitor Dell P2419H',
            'marca' => 'Dell',
            'modelo' => 'P2419H',
            'numero_serie' => 'SN-DEL-2419-01',
            'codigo_barras' => 'EQ-DEL-P2419-01',
            'ubicacion_id' => $this->ubicacion->id,
            'estado' => 'dado_de_baja',
            'criticidad' => 'baja',
        ]);

        $this->actingAs($this->panolero1);

        Volt::test('pages.bajas.index')
            ->call('abrirModalProponer')
            ->set('codigoBarras', $equipo->codigo_barras)
            ->assertSee('ya fue dado de baja formalmente')
            ->assertSet('equipo_id', null)
            ->call('guardarPropuesta')
            ->assertHasErrors(['codigoBarras']);
    }

    public function test_escaneo_equipo_con_propuesta_pendiente_muestra_error_y_bloquea_envio(): void
    {
        $equipo = Equipo::create([
            'nombre' => 'Servidor Dell PowerEdge R640',
            'marca' => 'Dell',
            'modelo' => 'R640',
            'numero_serie' => 'SN-DEL-R640-01',
            'codigo_barras' => 'EQ-DEL-R640-01',
            'ubicacion_id' => $this->ubicacion->id,
            'estado' => 'pendiente_resolucion',
            'criticidad' => 'alta',
        ]);

        $this->actingAs($this->panolero1);

        Volt::test('pages.bajas.index')
            ->call('abrirModalProponer')
            ->set('codigoBarras', $equipo->codigo_barras)
            ->assertSee('ya tiene una propuesta de baja pendiente de resolución')
            ->assertSet('equipo_id', null)
            ->call('guardarPropuesta')
            ->assertHasErrors(['codigoBarras']);
    }

    public function test_escaneo_equipo_en_prestamo_activo_muestra_error_y_bloquea_envio(): void
    {
        $equipo = Equipo::create([
            'nombre' => 'Osciloscopio Tektronix TBS1052B',
            'marca' => 'Tektronix',
            'modelo' => 'TBS1052B',
            'numero_serie' => 'SN-TEK-1052-01',
            'codigo_barras' => 'EQ-TEK-1052-01',
            'ubicacion_id' => $this->ubicacion->id,
            'estado' => 'prestado',
            'criticidad' => 'alta',
        ]);

        $this->actingAs($this->panolero1);

        Volt::test('pages.bajas.index')
            ->call('abrirModalProponer')
            ->set('codigoBarras', $equipo->codigo_barras)
            ->assertSee('está actualmente prestado')
            ->assertSet('equipo_id', null)
            ->call('guardarPropuesta')
            ->assertHasErrors(['codigoBarras']);
    }

    public function test_escaneo_equipo_valido_permite_formalizar_propuesta_de_baja(): void
    {
        $equipo = Equipo::create([
            'nombre' => 'Generador de Funciones Rigol DG1022Z',
            'marca' => 'Rigol',
            'modelo' => 'DG1022Z',
            'numero_serie' => 'SN-RIG-1022-01',
            'codigo_barras' => 'EQ-RIG-1022-01',
            'ubicacion_id' => $this->ubicacion->id,
            'estado' => 'disponible',
            'criticidad' => 'alta',
        ]);

        $this->actingAs($this->panolero1);

        Volt::test('pages.bajas.index')
            ->call('abrirModalProponer')
            ->set('codigoBarras', $equipo->codigo_barras)
            ->assertSet('errorEquipo', null)
            ->assertSet('equipo_id', $equipo->id)
            ->assertSee('Generador de Funciones Rigol DG1022Z')
            ->set('tipo', 'baja')
            ->set('motivo', 'Fallo irreversible en la fuente y DAC principal; reparación no conveniente.')
            ->call('guardarPropuesta')
            ->assertHasNoErrors()
            ->assertSet('mostrarModalProponer', false);

        $this->assertDatabaseHas('bajas_reemplazos', [
            'equipo_id' => $equipo->id,
            'propuesta_por' => $this->panolero1->id,
            'tipo' => 'baja',
            'estado' => 'propuesta',
        ]);

        $equipo->refresh();
        $this->assertEquals('pendiente_resolucion', $equipo->estado);
    }

    public function test_baja_policy_view_aisla_acceso_entre_panoleros(): void
    {
        $equipo = Equipo::create([
            'nombre' => 'Tester Digital Fluke 179',
            'marca' => 'Fluke',
            'modelo' => '179',
            'numero_serie' => 'SN-FLK-179-01',
            'codigo_barras' => 'EQ-FLK-179-01',
            'ubicacion_id' => $this->ubicacion->id,
            'estado' => 'pendiente_resolucion',
            'criticidad' => 'media',
        ]);

        $propuestaPanolero1 = BajaReemplazo::create([
            'equipo_id' => $equipo->id,
            'tipo' => 'baja',
            'motivo' => 'Tester descalibrado e irreparable según informe de laboratorio.',
            'estado' => 'propuesta',
            'propuesta_por' => $this->panolero1->id,
            'fecha' => now()->toDateString(),
        ]);

        // Pañolero 1 puede ver su propia propuesta
        $this->assertTrue(Gate::forUser($this->panolero1)->allows('view', $propuestaPanolero1));

        // Pañolero 2 NO puede ver la propuesta de Pañolero 1
        $this->assertFalse(Gate::forUser($this->panolero2)->allows('view', $propuestaPanolero1));

        // Administrador SÍ puede ver cualquier propuesta
        $this->assertTrue(Gate::forUser($this->admin)->allows('view', $propuestaPanolero1));
    }
}
