<?php

namespace Tests\Feature\HitoSiete;

use App\Models\Carrera;
use App\Models\DetalleSolicitud;
use App\Models\Equipo;
use App\Models\ParametroSistema;
use App\Models\SolicitudMaterial;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\PrestamoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CasoPruebaCP26Test extends TestCase
{
    use RefreshDatabase;

    protected User $panolero;

    protected Carrera $carrera;

    protected Ubicacion $bodega;

    protected Ubicacion $laboratorio;

    protected PrestamoService $prestamoService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prestamoService = app(PrestamoService::class);

        $this->panolero = User::factory()->create([
            'name' => 'Pañolero Operativo',
            'email' => 'operativo.panol@inacap.cl',
            'rol' => 'encargado_panol',
            'activo' => true,
        ]);

        $this->carrera = Carrera::create([
            'codigo' => 'AUT-01',
            'nombre' => 'Ingeniería en Automatización y Control',
            'activa' => true,
        ]);

        $this->bodega = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Pañol Automatización',
            'tipo' => 'panol',
        ]);

        $this->laboratorio = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Laboratorio PLC y Robótica',
            'tipo' => 'laboratorio',
        ]);

        ParametroSistema::set('stock_minimo_clases_default', 1, 'integer', 'Reserva mínima para clases');
    }

    /**
     * CP-26: Validación en servicio: exige RUT, verificación de credencial y hora acordada para préstamos a estudiantes.
     */
    public function test_servicio_exige_rut_credencial_verificada_y_hora_devolucion_para_estudiante(): void
    {
        $equipo = Equipo::create([
            'codigo_barras' => 'EQ-AUT-001',
            'nombre' => 'Kit PLC Siemens S7-1200',
            'marca' => 'Siemens',
            'modelo' => 'S7-1200',
            'numero_serie' => 'SN-SIEMENS-001',
            'estado' => 'disponible',
            'ubicacion_id' => $this->bodega->id,
            'reservado_clases' => false,
        ]);

        // Crear otro equipo idéntico para cumplir el umbral de reserva de clases (umbral = 1)
        Equipo::create([
            'codigo_barras' => 'EQ-AUT-002',
            'nombre' => 'Kit PLC Siemens S7-1200',
            'marca' => 'Siemens',
            'modelo' => 'S7-1200',
            'numero_serie' => 'SN-SIEMENS-002',
            'estado' => 'disponible',
            'ubicacion_id' => $this->bodega->id,
            'reservado_clases' => false,
        ]);

        $solicitud = SolicitudMaterial::create([
            'solicitante_id' => $this->panolero->id,
            'estado' => 'aprobada',
            'fecha_solicitud' => now(),
            'fecha_requerida' => now()->toDateString(),
            'ubicacion_destino_id' => $this->laboratorio->id,
        ]);

        $detalle = DetalleSolicitud::create([
            'solicitud_id' => $solicitud->id,
            'equipo_id' => $equipo->id,
            'cantidad_solicitada' => 1.0,
            'cantidad_entregada' => 0.0,
            'cantidad_devuelta' => 0.0,
            'estado_devolucion' => 'pendiente',
        ]);

        // 1. Falla por RUT vacío
        try {
            $this->prestamoService->registrarPrestamoEstudiante(
                solicitud: $solicitud,
                entregador: $this->panolero,
                estudianteNombre: 'Estudiante Matías Morales',
                credencialRut: '',
                credencialVerificada: true,
                horaEntrega: '10:00:00',
                horaDevolucionAcordada: '12:00:00',
                detallesAEntregar: [$detalle->id]
            );
            $this->fail('Se esperaba ValidationException por RUT vacío');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('receptor_credencial_rut', $e->errors());
        }

        // 2. Falla por credencial no verificada (checkbox false)
        try {
            $this->prestamoService->registrarPrestamoEstudiante(
                solicitud: $solicitud,
                entregador: $this->panolero,
                estudianteNombre: 'Estudiante Matías Morales',
                credencialRut: '20.345.678-9',
                credencialVerificada: false,
                horaEntrega: '10:00:00',
                horaDevolucionAcordada: '12:00:00',
                detallesAEntregar: [$detalle->id]
            );
            $this->fail('Se esperaba ValidationException por credencial no verificada');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('credencial_verificada', $e->errors());
        }

        // 3. Falla por hora de devolución vacía
        try {
            $this->prestamoService->registrarPrestamoEstudiante(
                solicitud: $solicitud,
                entregador: $this->panolero,
                estudianteNombre: 'Estudiante Matías Morales',
                credencialRut: '20.345.678-9',
                credencialVerificada: true,
                horaEntrega: '10:00:00',
                horaDevolucionAcordada: '',
                detallesAEntregar: [$detalle->id]
            );
            $this->fail('Se esperaba ValidationException por hora de devolución acordada');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('hora_devolucion_acordada', $e->errors());
        }
    }

    /**
     * CP-26: Préstamo exitoso a estudiante registra todos los datos de auditoría y actualiza el inventario.
     */
    public function test_prestamo_exitoso_a_estudiante_con_credencial_y_compromiso_horario(): void
    {
        $equipo = Equipo::create([
            'codigo_barras' => 'EQ-AUT-003',
            'nombre' => 'Módulo Entrenador Arduino',
            'marca' => 'Arduino',
            'modelo' => 'Education Kit',
            'numero_serie' => 'SN-ARD-003',
            'estado' => 'disponible',
            'ubicacion_id' => $this->bodega->id,
            'reservado_clases' => false,
        ]);

        // Segundo equipo para respetar stock_minimo_clases_default = 1
        Equipo::create([
            'codigo_barras' => 'EQ-AUT-004',
            'nombre' => 'Módulo Entrenador Arduino',
            'marca' => 'Arduino',
            'modelo' => 'Education Kit',
            'numero_serie' => 'SN-ARD-004',
            'estado' => 'disponible',
            'ubicacion_id' => $this->bodega->id,
            'reservado_clases' => false,
        ]);

        $solicitud = SolicitudMaterial::create([
            'solicitante_id' => $this->panolero->id,
            'estado' => 'aprobada',
            'fecha_solicitud' => now(),
            'fecha_requerida' => now()->toDateString(),
            'ubicacion_destino_id' => $this->laboratorio->id,
        ]);

        $detalle = DetalleSolicitud::create([
            'solicitud_id' => $solicitud->id,
            'equipo_id' => $equipo->id,
            'cantidad_solicitada' => 1.0,
            'cantidad_entregada' => 0.0,
            'cantidad_devuelta' => 0.0,
            'estado_devolucion' => 'pendiente',
        ]);

        $entrega = $this->prestamoService->registrarPrestamoEstudiante(
            solicitud: $solicitud,
            entregador: $this->panolero,
            estudianteNombre: 'Estudiante Rodrigo Araya',
            credencialRut: '18.765.432-1',
            credencialVerificada: true,
            horaEntrega: '14:15:00',
            horaDevolucionAcordada: '17:30:00',
            detallesAEntregar: [$detalle->id]
        );

        // Verificaciones en Base de Datos
        $this->assertDatabaseHas('entregas', [
            'id' => $entrega->id,
            'solicitud_id' => $solicitud->id,
            'entregado_por' => $this->panolero->id,
            'receptor_nombre' => 'Estudiante Rodrigo Araya',
            'receptor_tipo' => 'estudiante',
            'receptor_credencial_rut' => '18.765.432-1',
            'credencial_verificada' => true,
        ]);

        $this->assertEquals('17:30:00', (string) $entrega->hora_devolucion_acordada);
        $this->assertEquals('prestado', $equipo->fresh()->estado);
        $this->assertEquals(1.0, (float) $detalle->fresh()->cantidad_entregada);
    }

    /**
     * CP-26: Validación e interacción en componente Livewire de Despacho para préstamo a estudiante.
     */
    public function test_livewire_despacho_valida_formulario_estudiante_y_registra_entrega(): void
    {
        $this->actingAs($this->panolero);

        $equipo1 = Equipo::create([
            'codigo_barras' => 'EQ-AUT-005',
            'nombre' => 'Osciloscopio Automotriz Hantek',
            'marca' => 'Hantek',
            'modelo' => '2D82AUTO',
            'numero_serie' => 'SN-HTK-005',
            'estado' => 'disponible',
            'ubicacion_id' => $this->bodega->id,
            'reservado_clases' => false,
        ]);

        // Segundo osciloscopio idéntico para que quede disponible >= 1
        Equipo::create([
            'codigo_barras' => 'EQ-AUT-006',
            'nombre' => 'Osciloscopio Automotriz Hantek',
            'marca' => 'Hantek',
            'modelo' => '2D82AUTO',
            'numero_serie' => 'SN-HTK-006',
            'estado' => 'disponible',
            'ubicacion_id' => $this->bodega->id,
            'reservado_clases' => false,
        ]);

        $solicitud = SolicitudMaterial::create([
            'solicitante_id' => $this->panolero->id,
            'estado' => 'aprobada',
            'fecha_solicitud' => now(),
            'fecha_requerida' => now()->toDateString(),
            'ubicacion_destino_id' => $this->laboratorio->id,
        ]);

        $detalle = DetalleSolicitud::create([
            'solicitud_id' => $solicitud->id,
            'equipo_id' => $equipo1->id,
            'cantidad_solicitada' => 1.0,
            'cantidad_entregada' => 0.0,
            'cantidad_devuelta' => 0.0,
            'estado_devolucion' => 'pendiente',
        ]);

        // Intentar registrar sin RUT y sin confirmar credencial
        Volt::test('pages.despacho.index')
            ->call('abrirModalEntrega', $solicitud->id)
            ->set('receptorTipo', 'estudiante')
            ->set('receptorNombre', 'Estudiante Andrés Castro')
            ->set('receptorCredencialRut', '')
            ->set('credencialVerificada', false)
            ->set('detallesSeleccionadosIds', [$detalle->id])
            ->call('confirmarEntrega')
            ->assertHasErrors(['receptorCredencialRut', 'credencialVerificada']);

        // Completar correctamente el formulario y confirmar despacho
        Volt::test('pages.despacho.index')
            ->call('abrirModalEntrega', $solicitud->id)
            ->set('receptorTipo', 'estudiante')
            ->set('receptorNombre', 'Estudiante Andrés Castro')
            ->set('receptorCredencialRut', '17.654.321-0')
            ->set('credencialVerificada', true)
            ->set('horaDevolucionAcordada', '16:00')
            ->set('detallesSeleccionadosIds', [$detalle->id])
            ->call('confirmarEntrega')
            ->assertHasNoErrors()
            ->assertSee('¡Despacho confirmado exitosamente!');

        $this->assertDatabaseHas('entregas', [
            'solicitud_id' => $solicitud->id,
            'receptor_nombre' => 'Estudiante Andrés Castro',
            'receptor_credencial_rut' => '17.654.321-0',
            'credencial_verificada' => true,
        ]);

        $this->assertEquals('prestado', $equipo1->fresh()->estado);
    }
}
