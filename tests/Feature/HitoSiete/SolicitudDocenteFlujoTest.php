<?php

namespace Tests\Feature\HitoSiete;

use App\Models\Asignatura;
use App\Models\Carrera;
use App\Models\Equipo;
use App\Models\Insumo;
use App\Models\SolicitudMaterial;
use App\Models\Ubicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SolicitudDocenteFlujoTest extends TestCase
{
    use RefreshDatabase;

    protected User $docente;

    protected Carrera $carrera;

    protected Asignatura $asignatura;

    protected Ubicacion $panol;

    protected Ubicacion $laboratorio;

    protected Insumo $insumo;

    protected Equipo $equipo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->carrera = Carrera::create([
            'codigo' => 'INF-ING',
            'nombre' => 'Ingeniería en Informática',
            'activo' => true,
        ]);

        $this->docente = User::factory()->create([
            'nombre' => 'Juan Salazar',
            'email' => 'docente@inacap.cl',
            'rol' => 'docente',
            'activo' => true,
        ]);

        $this->docente->carreras()->attach($this->carrera->id);

        $this->asignatura = Asignatura::create([
            'carrera_id' => $this->carrera->id,
            'codigo' => 'INF-101',
            'nombre' => 'POO1',
            'activo' => true,
        ]);

        $this->docente->asignaturas()->attach($this->asignatura->id);

        $this->panol = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Pañol Central',
            'tipo' => 'panol',
        ]);

        $this->laboratorio = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Laboratorio L1 - Redes y Telecomunicaciones',
            'tipo' => 'laboratorio',
        ]);

        $this->insumo = Insumo::create([
            'carrera_id' => $this->carrera->id,
            'ubicacion_id' => $this->panol->id,
            'codigo_barras' => 'INS-TEST-001',
            'nombre' => 'Cable UTP Cat6',
            'unidad' => 'metro',
            'stock_actual' => 100,
            'stock_minimo' => 10,
            'stock_minimo_clases' => 5,
            'es_retornable' => false,
            'activo' => true,
        ]);

        $this->equipo = Equipo::create([
            'carrera_id' => $this->carrera->id,
            'ubicacion_id' => $this->panol->id,
            'codigo_barras' => 'EQ-TEST-001',
            'numero_serie' => 'SN-TEST-123',
            'nombre' => 'Osciloscopio Digital',
            'marca' => 'Tektronix',
            'modelo' => 'TBS1052B',
            'estado' => 'disponible',
            'criticidad' => 'alta',
            'activo' => true,
        ]);
    }

    /**
     * El docente conmuta entre laboratorio y sala mediante seleccionarTipoDestino sin error de lifecycle.
     */
    public function test_docente_conmuta_tipo_destino_entre_laboratorio_y_sala(): void
    {
        $this->actingAs($this->docente);

        Volt::test('pages.solicitudes.index')
            ->call('abrirModalCrear')
            ->assertSet('tipo_destino', 'laboratorio')
            ->call('seleccionarTipoDestino', 'sala')
            ->assertSet('tipo_destino', 'sala')
            ->assertSet('edificio_destino', 'Edificio A')
            ->assertSet('sala_destino', 'A101')
            ->assertSet('ubicacion_destino_id', null)
            ->call('seleccionarTipoDestino', 'laboratorio')
            ->assertSet('tipo_destino', 'laboratorio');
    }

    /**
     * El docente visualiza sus asignaturas y puede solicitar a una Sala con Edificio A o C.
     */
    public function test_docente_crea_solicitud_para_sala_en_edificio_a(): void
    {
        $this->actingAs($this->docente);

        Volt::test('pages.solicitudes.index')
            ->call('abrirModalCrear')
            ->set('fecha_requerida', Carbon::tomorrow()->toDateString())
            ->set('hora_requerida', '09:00')
            ->set('asignatura_seleccionada', (string) $this->asignatura->id)
            ->set('tipo_destino', 'sala')
            ->set('edificio_destino', 'Edificio A')
            ->set('sala_destino', 'A102')
            ->set('itemsSeleccionados', [
                ['tipo' => 'insumo', 'id' => $this->insumo->id, 'nombre' => $this->insumo->nombre, 'cantidad' => 10.0, 'es_retornable' => false],
            ])
            ->call('registrarSolicitud')
            ->assertHasNoErrors()
            ->assertSee('Solicitud registrada exitosamente');

        $this->assertDatabaseHas('solicitudes_material', [
            'solicitante_id' => $this->docente->id,
            'asignatura_id' => $this->asignatura->id,
            'tipo_destino' => 'sala',
            'edificio_destino' => 'Edificio A',
            'espacio_destino' => 'Edificio A - A102',
            'estado' => 'pendiente',
        ]);
    }

    /**
     * El docente selecciona "otra actividad" y el sistema exige observaciones.
     */
    public function test_docente_selecciona_otra_actividad_requiere_observaciones(): void
    {
        $this->actingAs($this->docente);

        // Sin observaciones -> error
        Volt::test('pages.solicitudes.index')
            ->call('abrirModalCrear')
            ->set('fecha_requerida', Carbon::tomorrow()->toDateString())
            ->set('hora_requerida', '11:00')
            ->set('asignatura_seleccionada', 'otra')
            ->set('observaciones', '')
            ->set('tipo_destino', 'sala')
            ->set('edificio_destino', 'Edificio C')
            ->set('sala_destino', 'C201')
            ->set('itemsSeleccionados', [
                ['tipo' => 'insumo', 'id' => $this->insumo->id, 'nombre' => $this->insumo->nombre, 'cantidad' => 5.0, 'es_retornable' => false],
            ])
            ->call('registrarSolicitud')
            ->assertHasErrors(['observaciones']);

        // Con observaciones -> éxito
        Volt::test('pages.solicitudes.index')
            ->call('abrirModalCrear')
            ->set('fecha_requerida', Carbon::tomorrow()->toDateString())
            ->set('hora_requerida', '11:00')
            ->set('asignatura_seleccionada', 'otra')
            ->set('observaciones', 'Taller extracurricular de robótica y sensores')
            ->set('tipo_destino', 'sala')
            ->set('edificio_destino', 'Edificio C')
            ->set('sala_destino', 'C201')
            ->set('itemsSeleccionados', [
                ['tipo' => 'insumo', 'id' => $this->insumo->id, 'nombre' => $this->insumo->nombre, 'cantidad' => 5.0, 'es_retornable' => false],
            ])
            ->call('registrarSolicitud')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('solicitudes_material', [
            'solicitante_id' => $this->docente->id,
            'asignatura_id' => null,
            'observaciones' => 'Taller extracurricular de robótica y sensores',
            'espacio_destino' => 'Edificio C - C201',
        ]);
    }

    /**
     * La sección Mis Solicitudes muestra estado 'Solicitado' y permite cancelar.
     */
    public function test_docente_puede_cancelar_solicitud_solicitada(): void
    {
        $this->actingAs($this->docente);

        $solicitud = SolicitudMaterial::create([
            'solicitante_id' => $this->docente->id,
            'asignatura_id' => $this->asignatura->id,
            'tipo_destino' => 'sala',
            'edificio_destino' => 'Edificio A',
            'espacio_destino' => 'Edificio A - Sala A-202',
            'fecha_solicitud' => now(),
            'fecha_requerida' => Carbon::tomorrow()->toDateString(),
            'hora_requerida' => '08:30:00',
            'estado' => 'pendiente',
        ]);

        $solicitud->detalles()->create([
            'insumo_id' => $this->insumo->id,
            'cantidad_solicitada' => 2,
            'cantidad_entregada' => 0,
            'cantidad_devuelta' => 0,
            'estado_devolucion' => 'no_aplica',
        ]);

        // Verificamos que se renderice 'Solicitado' y 'Cancelar solicitud'
        Volt::test('pages.solicitudes.index')
            ->assertSee('Mis Solicitudes')
            ->assertDontSee('Mis Solicitudes Realizadas')
            ->assertSee('Solicitado')
            ->assertSee('Cancelar solicitud')
            ->call('cancelarSolicitud', $solicitud->id)
            ->assertSee('cancelada exitosamente');

        $this->assertDatabaseHas('solicitudes_material', [
            'id' => $solicitud->id,
            'estado' => 'cancelada',
        ]);
    }
}
