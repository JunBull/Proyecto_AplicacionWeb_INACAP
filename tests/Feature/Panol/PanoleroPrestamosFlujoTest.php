<?php

namespace Tests\Feature\Panol;

use App\Mail\AlertaRetrasoEstudianteMail;
use App\Models\Carrera;
use App\Models\DetalleSolicitud;
use App\Models\Entrega;
use App\Models\Equipo;
use App\Models\SolicitudMaterial;
use App\Models\Ubicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PanoleroPrestamosFlujoTest extends TestCase
{
    use RefreshDatabase;

    protected User $panolero;

    protected User $docente;

    protected Carrera $carrera;

    protected Ubicacion $panol;

    protected Ubicacion $laboratorio;

    protected Equipo $equipo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->panolero = User::factory()->create([
            'name' => 'Mario Pañolero',
            'email' => 'mario.panolero@inacap.cl',
            'rol' => 'encargado_panol',
            'activo' => true,
        ]);

        $this->docente = User::factory()->create([
            'name' => 'Profesor Juan Salazar',
            'email' => 'docente@inacap.cl',
            'rol' => 'docente',
            'activo' => true,
        ]);

        $this->carrera = Carrera::create([
            'codigo' => 'INF-ING',
            'nombre' => 'Ingeniería en Informática',
            'activa' => true,
        ]);

        $this->panol = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Pañol Central de Informática',
            'tipo' => 'panol',
        ]);

        $this->laboratorio = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Laboratorio L1',
            'tipo' => 'laboratorio',
        ]);

        $this->equipo = Equipo::create([
            'codigo_barras' => 'EQ-TEST-001',
            'nombre' => 'Notebook Dell Latitude',
            'marca' => 'Dell',
            'modelo' => '5420',
            'numero_serie' => 'SN-DELL-001',
            'estado' => 'disponible',
            'ubicacion_id' => $this->panol->id,
            'reservado_clases' => false,
        ]);
    }

    public function test_vista_prestamos_muestra_titulo_y_boton_simplificados_sin_subtitulos(): void
    {
        $this->actingAs($this->panolero);

        $response = $this->get(route('panol.despacho'));
        $response->assertOk();
        $response->assertSee('Préstamos');
        $response->assertSee('Préstamo Alumno');
        $response->assertDontSee('+ Préstamo Alumno');
        $response->assertDontSee('Revisión de solicitudes anticipadas, control de entregas');

        // Formato amigable de rol en navbar
        $response->assertSee('Pañolero');
        $response->assertDontSee('ENCARGADO_PANOL');
    }

    public function test_modal_prestamo_alumno_permite_elegir_sala_o_laboratorio(): void
    {
        $this->actingAs($this->panolero);

        // Crear equipo adicional con el mismo nombre para superar el piso de reserva docente
        Equipo::create([
            'codigo_barras' => 'EQ-TEST-002',
            'nombre' => 'Notebook Dell Latitude',
            'marca' => 'Dell',
            'modelo' => '5420',
            'numero_serie' => 'SN-DELL-002',
            'estado' => 'disponible',
            'ubicacion_id' => $this->panol->id,
            'reservado_clases' => false,
        ]);

        // Alumno solicita en mesón para Sala en Edificio C
        $component = Volt::test('pages.despacho.index')
            ->call('abrirModalPresencial')
            ->assertSee('Sala / Laboratorio de Destino')
            ->assertSee('SALA')
            ->assertSee('LABORATORIO')
            ->assertSet('presencialTipoDestino', 'laboratorio')
            ->call('seleccionarTipoDestinoPresencial', 'sala')
            ->assertSet('presencialTipoDestino', 'sala')
            ->assertSet('presencialEdificioDestino', 'Edificio A')
            ->set('presencialEdificioDestino', 'Edificio C')
            ->assertSet('presencialSalaDestino', 'C101')
            ->set('presencialNombre', 'Carlos Alumno Test')
            ->set('presencialRut', '20.123.456-7')
            ->set('presencialCredencialVerificada', true)
            ->set('presencialHoraDevolucion', '17:00')
            ->call('agregarItemPresencial', 'equipo', $this->equipo->id)
            ->call('confirmarPrestamoPresencial')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('solicitudes_material', [
            'tipo_destino' => 'sala',
            'edificio_destino' => 'Edificio C',
            'espacio_destino' => 'Edificio C - C101',
        ]);

        $this->assertDatabaseHas('entregas', [
            'receptor_nombre' => 'Carlos Alumno Test',
            'receptor_credencial_rut' => '20.123.456-7',
        ]);
    }

    public function test_tabs_abiertas_vencidas_e_historial_con_buscador_reactivo(): void
    {
        $this->actingAs($this->panolero);

        // Crear una solicitud docente abierta
        $solicitud = SolicitudMaterial::create([
            'solicitante_id' => $this->docente->id,
            'fecha_solicitud' => now(),
            'fecha_requerida' => now()->addDay(),
            'hora_requerida' => '09:30:00',
            'ubicacion_destino_id' => $this->laboratorio->id,
            'estado' => 'pendiente',
            'observaciones' => 'Práctica de redes y sistemas',
        ]);

        DetalleSolicitud::create([
            'solicitud_id' => $solicitud->id,
            'equipo_id' => $this->equipo->id,
            'cantidad_solicitada' => 1.0,
            'cantidad_entregada' => 0.0,
            'cantidad_devuelta' => 0.0,
            'estado_devolucion' => 'pendiente',
        ]);

        $component = Volt::test('pages.despacho.index')
            ->assertSee('Abiertas')
            ->assertSee('Vencidas')
            ->assertSee('Historial')
            ->assertSee('Profesor Juan Salazar')
            ->assertSee('Entregar');

        // Filtrar por término que no coincide
        $component->set('busquedaRapida', 'TextoInexistente123')
            ->assertDontSee('Profesor Juan Salazar');

        // Restaurar búsqueda y verificar que reaparece
        $component->set('busquedaRapida', 'Salazar')
            ->assertSee('Profesor Juan Salazar');
    }

    public function test_tab_vencidas_detecta_prestamos_a_estudiantes_con_hora_expirada(): void
    {
        $this->actingAs($this->panolero);

        // Crear solicitud y entrega a estudiante vencida (hora acordada pasada)
        $solicitud = SolicitudMaterial::create([
            'solicitante_id' => $this->panolero->id,
            'fecha_solicitud' => now()->subHours(4),
            'fecha_requerida' => now()->toDateString(),
            'hora_requerida' => now()->subHours(4)->format('H:i:s'),
            'ubicacion_destino_id' => $this->laboratorio->id,
            'estado' => 'entregada_abierta',
        ]);

        DetalleSolicitud::create([
            'solicitud_id' => $solicitud->id,
            'equipo_id' => $this->equipo->id,
            'cantidad_solicitada' => 1.0,
            'cantidad_entregada' => 1.0,
            'cantidad_devuelta' => 0.0,
            'estado_devolucion' => 'pendiente',
        ]);

        Entrega::create([
            'solicitud_id' => $solicitud->id,
            'entregado_por' => $this->panolero->id,
            'receptor_nombre' => 'Alumno Pedro Valdés',
            'receptor_tipo' => 'estudiante',
            'receptor_credencial_rut' => '21.456.789-0',
            'credencial_verificada' => true,
            'hora_entrega' => now()->subHours(4)->format('H:i:s'),
            'hora_devolucion_acordada' => now()->subHours(2)->format('H:i:s'),
            'fecha_entrega' => now()->subHours(4),
        ]);

        Volt::test('pages.despacho.index')
            ->set('pestanaActiva', 'vencidas')
            ->assertSee('Alumno Pedro Valdés')
            ->assertSee('21.456.789-0')
            ->assertSee('VENCIDO')
            ->assertSee('Recibir Devolución');
    }

    public function test_modulo_independiente_devoluciones_renderiza_y_procesa(): void
    {
        $this->actingAs($this->panolero);

        $response = $this->get(route('panol.devoluciones'));
        $response->assertOk();
        $response->assertSee('Devoluciones');

        $solicitud = SolicitudMaterial::create([
            'solicitante_id' => $this->docente->id,
            'fecha_solicitud' => now(),
            'fecha_requerida' => now()->toDateString(),
            'ubicacion_destino_id' => $this->laboratorio->id,
            'estado' => 'entregada_abierta',
        ]);

        $detalle = DetalleSolicitud::create([
            'solicitud_id' => $solicitud->id,
            'equipo_id' => $this->equipo->id,
            'cantidad_solicitada' => 1.0,
            'cantidad_entregada' => 1.0,
            'cantidad_devuelta' => 0.0,
            'estado_devolucion' => 'pendiente',
        ]);

        $entrega = Entrega::create([
            'solicitud_id' => $solicitud->id,
            'entregado_por' => $this->panolero->id,
            'receptor_nombre' => 'Profesor Juan Salazar',
            'receptor_tipo' => 'docente',
            'fecha_entrega' => now(),
        ]);

        Volt::test('pages.devoluciones.index')
            ->assertSee('Profesor Juan Salazar')
            ->call('abrirModalDevolucion', $entrega->id)
            ->call('confirmarDevolucion')
            ->assertHasNoErrors()
            ->assertSee('¡Devolución procesada exitosamente!');

        $this->assertEquals('disponible', $this->equipo->fresh()->estado);
        $this->assertEquals('cerrada', $solicitud->fresh()->estado);
    }

    public function test_prestamo_estudiante_vencido_muestra_boton_reportar_y_envia_correo_a_administradores(): void
    {
        $this->actingAs($this->panolero);

        $admin = User::factory()->create([
            'rol' => 'administrador',
            'email' => 'director.ti@inacap.cl',
            'activo' => true,
        ]);

        // 1. Solicitud y Entrega a Estudiante VENCIDA (debe mostrar botón REPORTAR)
        $solicitudEstudiante = SolicitudMaterial::create([
            'solicitante_id' => $this->panolero->id,
            'fecha_solicitud' => now()->subHours(4),
            'fecha_requerida' => now()->toDateString(),
            'ubicacion_destino_id' => $this->laboratorio->id,
            'estado' => 'entregada_abierta',
        ]);

        DetalleSolicitud::create([
            'solicitud_id' => $solicitudEstudiante->id,
            'equipo_id' => $this->equipo->id,
            'cantidad_solicitada' => 1.0,
            'cantidad_entregada' => 1.0,
            'cantidad_devuelta' => 0.0,
            'estado_devolucion' => 'pendiente',
        ]);

        $entregaEstudianteVencida = Entrega::create([
            'solicitud_id' => $solicitudEstudiante->id,
            'entregado_por' => $this->panolero->id,
            'receptor_nombre' => 'Matías Alarcón',
            'receptor_tipo' => 'estudiante',
            'receptor_credencial_rut' => '20.123.456-8',
            'credencial_verificada' => true,
            'hora_entrega' => now()->subHours(4)->format('H:i:s'),
            'hora_devolucion_acordada' => now()->subHours(2)->format('H:i:s'),
            'fecha_entrega' => now()->subHours(4),
        ]);

        // 2. Solicitud y Entrega a Docente vencida (NO debe mostrar botón REPORTAR porque es docente)
        $solicitudDocente = SolicitudMaterial::create([
            'solicitante_id' => $this->docente->id,
            'fecha_solicitud' => now()->subHours(4),
            'fecha_requerida' => now()->toDateString(),
            'ubicacion_destino_id' => $this->laboratorio->id,
            'estado' => 'entregada_abierta',
        ]);

        $equipoDocente = Equipo::create([
            'ubicacion_id' => $this->laboratorio->id,
            'codigo_barras' => 'EQ-DOC-99',
            'nombre' => 'Proyector Epson',
            'marca' => 'Epson',
            'modelo' => 'PowerLite X39',
            'numero_serie' => 'SN-EPS-99',
            'estado' => 'prestado',
            'criticidad' => 'media',
        ]);

        DetalleSolicitud::create([
            'solicitud_id' => $solicitudDocente->id,
            'equipo_id' => $equipoDocente->id,
            'cantidad_solicitada' => 1.0,
            'cantidad_entregada' => 1.0,
            'cantidad_devuelta' => 0.0,
            'estado_devolucion' => 'pendiente',
        ]);

        $entregaDocente = Entrega::create([
            'solicitud_id' => $solicitudDocente->id,
            'entregado_por' => $this->panolero->id,
            'receptor_nombre' => 'Profesor Carlos Valdés',
            'receptor_tipo' => 'docente',
            'hora_entrega' => now()->subHours(4)->format('H:i:s'),
            'hora_devolucion_acordada' => now()->subHours(2)->format('H:i:s'),
            'fecha_entrega' => now()->subHours(4),
        ]);

        Mail::fake();

        $test = Volt::test('pages.devoluciones.index')
            ->assertSee('Matías Alarcón')
            ->assertSee('Profesor Carlos Valdés')
            ->assertSee('REPORTAR');

        // Ejecutar reporte de retraso a estudiante
        $test->call('reportarRetraso', $entregaEstudianteVencida->id)
            ->assertHasNoErrors()
            ->assertSee('Se ha enviado la alerta de retraso por correo a los administradores')
            ->assertSee('Reportado');

        // Verificar que el correo fue enviado a los administradores con la clase Mailable
        Mail::assertSent(AlertaRetrasoEstudianteMail::class, function (AlertaRetrasoEstudianteMail $mail) use ($admin, $entregaEstudianteVencida) {
            return $mail->hasTo($admin->email)
                && $mail->entrega->id === $entregaEstudianteVencida->id
                && str_contains($mail->envelope()->subject, 'Matías Alarcón');
        });

        // Verificar que la entrega fue marcada como reportada
        $this->assertNotNull($entregaEstudianteVencida->fresh()->reportado_en);
    }
}
