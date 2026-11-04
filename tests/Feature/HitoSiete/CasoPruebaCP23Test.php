<?php

namespace Tests\Feature\HitoSiete;

use App\Models\Asignatura;
use App\Models\Carrera;
use App\Models\Insumo;
use App\Models\SolicitudMaterial;
use App\Models\Ubicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CasoPruebaCP23Test extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $docente;

    protected Carrera $carrera;

    protected Asignatura $asignaturaRedes;

    protected Asignatura $asignaturaSoftware;

    protected Ubicacion $laboratorio;

    protected Insumo $insumo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Administrador Central',
            'email' => 'admin@inacap.cl',
            'rol' => 'administrador',
            'activo' => true,
        ]);

        $this->docente = User::factory()->create([
            'name' => 'Profesor Carlos Valenzuela',
            'email' => 'carlos.valenzuela@inacap.cl',
            'rol' => 'docente',
            'activo' => true,
        ]);

        $this->carrera = Carrera::create([
            'codigo' => 'INF-01',
            'nombre' => 'Ingeniería en Informática',
            'activa' => true,
        ]);

        $this->asignaturaRedes = Asignatura::create([
            'carrera_id' => $this->carrera->id,
            'codigo' => 'INF-RED-101',
            'nombre' => 'Taller Práctico de Redes y Telecomunicaciones',
            'activo' => true,
        ]);

        $this->asignaturaSoftware = Asignatura::create([
            'carrera_id' => $this->carrera->id,
            'codigo' => 'INF-DEV-201',
            'nombre' => 'Laboratorio de Desarrollo Web Avanzado',
            'activo' => true,
        ]);

        $this->laboratorio = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Laboratorio L3 - Redes Cisco',
            'tipo' => 'laboratorio',
        ]);

        $this->insumo = Insumo::create([
            'codigo_barras' => 'INS-TEST-CAB-01',
            'nombre' => 'Cable Patch Cord Cat 6 2m',
            'unidad' => 'unidad',
            'es_retornable' => true,
            'stock_actual' => 30.0,
            'stock_minimo' => 5.0,
            'ubicacion_id' => $this->laboratorio->id,
        ]);
    }

    /**
     * CP-23: Asignar asignaturas/talleres a un docente desde el panel de Administración.
     */
    public function test_administrador_asigna_talleres_a_docente(): void
    {
        $this->actingAs($this->admin);

        Volt::test('pages.administracion.usuarios')
            ->call('abrirModalEditar', $this->docente->id)
            ->set('carrerasSeleccionadas', [$this->carrera->id])
            ->set('asignaturasSeleccionadas', [$this->asignaturaRedes->id, $this->asignaturaSoftware->id])
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('asignatura_usuario', [
            'usuario_id' => $this->docente->id,
            'asignatura_id' => $this->asignaturaRedes->id,
        ]);

        $this->assertDatabaseHas('asignatura_usuario', [
            'usuario_id' => $this->docente->id,
            'asignatura_id' => $this->asignaturaSoftware->id,
        ]);
    }

    /**
     * CP-23: El docente visualiza sus asignaturas asignadas y formaliza solicitud con sala y hora obligatorias.
     */
    public function test_docente_crea_solicitud_con_taller_sala_y_hora_requerida(): void
    {
        // Vincular previamente las asignaturas
        $this->docente->asignaturas()->sync([$this->asignaturaRedes->id]);

        $this->actingAs($this->docente);

        // 1. Intentar registrar sin hora requerida o sin sala -> debe fallar con validación
        Volt::test('pages.solicitudes.index')
            ->call('abrirModalCrear')
            ->set('itemsSeleccionados', [
                ['tipo' => 'insumo', 'id' => $this->insumo->id, 'nombre' => $this->insumo->nombre, 'cantidad' => 2.0, 'es_retornable' => true],
            ])
            ->set('hora_requerida', '')
            ->set('ubicacion_destino_id', null)
            ->call('registrarSolicitud')
            ->assertHasErrors(['hora_requerida', 'ubicacion_destino_id']);

        // 2. Registrar correctamente con todos los campos obligatorios v1.1
        Volt::test('pages.solicitudes.index')
            ->call('abrirModalCrear')
            ->set('fecha_requerida', Carbon::tomorrow()->toDateString())
            ->set('hora_requerida', '10:30')
            ->set('asignatura_id', $this->asignaturaRedes->id)
            ->set('ubicacion_destino_id', $this->laboratorio->id)
            ->set('itemsSeleccionados', [
                ['tipo' => 'insumo', 'id' => $this->insumo->id, 'nombre' => $this->insumo->nombre, 'cantidad' => 5.0, 'es_retornable' => true],
            ])
            ->call('registrarSolicitud')
            ->assertHasNoErrors()
            ->assertSee('Solicitud registrada exitosamente');

        $this->assertDatabaseHas('solicitudes_material', [
            'solicitante_id' => $this->docente->id,
            'asignatura_id' => $this->asignaturaRedes->id,
            'ubicacion_destino_id' => $this->laboratorio->id,
            'estado' => 'pendiente',
        ]);

        $solicitud = SolicitudMaterial::where('solicitante_id', $this->docente->id)->first();
        $this->assertNotNull($solicitud);
        $this->assertStringStartsWith('10:30', (string) $solicitud->hora_requerida);
    }
}
