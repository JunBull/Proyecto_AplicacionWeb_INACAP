<?php

namespace Tests\Feature\HitoSiete;

use App\Exceptions\StockReservadoClasesException;
use App\Models\Carrera;
use App\Models\Entrega;
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

class CasoPruebaPrestamoPresencialEstudianteTest extends TestCase
{
    use RefreshDatabase;

    protected User $panolero;

    protected Carrera $carrera;

    protected Ubicacion $panol;

    protected Ubicacion $laboratorio;

    protected PrestamoService $prestamoService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prestamoService = app(PrestamoService::class);

        $this->panolero = User::factory()->create([
            'name' => 'Pañolero Mesón',
            'email' => 'meson.panol@inacap.cl',
            'rol' => 'encargado_panol',
            'activo' => true,
        ]);

        $this->carrera = Carrera::create([
            'codigo' => 'MEC-01',
            'nombre' => 'Mecánica Automotriz y Autotrónica',
            'activa' => true,
        ]);

        $this->panol = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Pañol Central de Mecánica',
            'tipo' => 'panol',
        ]);

        $this->laboratorio = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Taller de Motores Pesados',
            'tipo' => 'laboratorio',
        ]);

        ParametroSistema::set('stock_minimo_clases_default', 1, 'integer', 'Piso mínimo para docencia');
    }

    /**
     * Prueba: Registro presencial directo en ventanilla a estudiante genera solicitud y entrega atómica.
     */
    public function test_panolero_registra_prestamo_presencial_exitoso_a_estudiante_en_un_solo_paso(): void
    {
        $equipo1 = Equipo::create([
            'codigo_barras' => 'EQ-MEC-PRES-001',
            'nombre' => 'Torquímetro Digital Snap-on',
            'marca' => 'Snap-on',
            'modelo' => 'ATECH2FR100B',
            'numero_serie' => 'SN-SNAP-001',
            'estado' => 'disponible',
            'ubicacion_id' => $this->panol->id,
            'reservado_clases' => false,
        ]);

        // Equipo adicional para cumplir reserva de clases (umbral = 1)
        Equipo::create([
            'codigo_barras' => 'EQ-MEC-PRES-002',
            'nombre' => 'Torquímetro Digital Snap-on',
            'marca' => 'Snap-on',
            'modelo' => 'ATECH2FR100B',
            'numero_serie' => 'SN-SNAP-002',
            'estado' => 'disponible',
            'ubicacion_id' => $this->panol->id,
            'reservado_clases' => false,
        ]);

        $entrega = $this->prestamoService->registrarPrestamoPresencialEnMeson(
            panolero: $this->panolero,
            datosEstudiante: [
                'nombre' => 'Estudiante Fabián Bustos',
                'rut' => '20.987.654-K',
                'credencial_verificada' => true,
                'hora_devolucion_acordada' => '16:30:00',
                'ubicacion_destino_id' => $this->laboratorio->id,
            ],
            items: [
                [
                    'tipo' => 'equipo',
                    'id' => $equipo1->id,
                    'cantidad' => 1.0,
                ],
            ]
        );

        // 1. Verificación de Entrega
        $this->assertDatabaseHas('entregas', [
            'id' => $entrega->id,
            'entregado_por' => $this->panolero->id,
            'receptor_nombre' => 'Estudiante Fabián Bustos',
            'receptor_tipo' => 'estudiante',
            'receptor_credencial_rut' => '20.987.654-K',
            'credencial_verificada' => true,
        ]);

        // 2. Verificación de Solicitud generada y enlazada automáticamente
        $solicitud = SolicitudMaterial::find($entrega->solicitud_id);
        $this->assertNotNull($solicitud);
        $this->assertEquals($this->panolero->id, $solicitud->solicitante_id);
        $this->assertEquals('entregada_abierta', $solicitud->estado);
        $this->assertStringContainsString('Fabián Bustos', $solicitud->observaciones);

        // 3. Verificación de estado del Activo
        $this->assertEquals('prestado', $equipo1->fresh()->estado);
    }

    /**
     * Prueba: Validación estricta exige RUT, retención física de credencial y hora pactada.
     */
    public function test_prestamo_presencial_falla_si_falta_rut_o_no_se_verifica_credencial_fisica(): void
    {
        $equipo = Equipo::create([
            'codigo_barras' => 'EQ-MEC-PRES-003',
            'nombre' => 'Compresímetro Diesel OTC',
            'marca' => 'OTC',
            'modelo' => '5021',
            'numero_serie' => 'SN-OTC-003',
            'estado' => 'disponible',
            'ubicacion_id' => $this->panol->id,
            'reservado_clases' => false,
        ]);

        // 1. Falla por RUT ausente
        try {
            $this->prestamoService->registrarPrestamoPresencialEnMeson(
                panolero: $this->panolero,
                datosEstudiante: [
                    'nombre' => 'Estudiante Sin Rut',
                    'rut' => '',
                    'credencial_verificada' => true,
                    'hora_devolucion_acordada' => '15:00:00',
                ],
                items: [['tipo' => 'equipo', 'id' => $equipo->id, 'cantidad' => 1]]
            );
            $this->fail('Se esperaba ValidationException por RUT vacío');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('receptor_credencial_rut', $e->errors());
        }

        // 2. Falla si no se confirma la retención física de la credencial
        try {
            $this->prestamoService->registrarPrestamoPresencialEnMeson(
                panolero: $this->panolero,
                datosEstudiante: [
                    'nombre' => 'Estudiante Sin Credencial',
                    'rut' => '19.876.543-2',
                    'credencial_verificada' => false,
                    'hora_devolucion_acordada' => '15:00:00',
                ],
                items: [['tipo' => 'equipo', 'id' => $equipo->id, 'cantidad' => 1]]
            );
            $this->fail('Se esperaba ValidationException por credencial no verificada');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('credencial_verificada', $e->errors());
        }
    }

    /**
     * Prueba: Bloquea entrega presencial si el material está reservado para docencia (CP-25).
     */
    public function test_prestamo_presencial_bloquea_equipos_o_insumos_reservados_para_clases(): void
    {
        $equipoReservado = Equipo::create([
            'codigo_barras' => 'EQ-MEC-PRES-004',
            'nombre' => 'Escáner Automotriz Bosch KTS 590',
            'marca' => 'Bosch',
            'modelo' => 'KTS 590',
            'numero_serie' => 'SN-BOSCH-004',
            'estado' => 'disponible',
            'ubicacion_id' => $this->panol->id,
            'reservado_clases' => true, // Marcado exclusivo para clases
        ]);

        $this->expectException(StockReservadoClasesException::class);
        $this->expectExceptionMessage('reservado exclusivamente para clases docentes');

        $this->prestamoService->registrarPrestamoPresencialEnMeson(
            panolero: $this->panolero,
            datosEstudiante: [
                'nombre' => 'Estudiante Ignacio Silva',
                'rut' => '21.345.678-9',
                'credencial_verificada' => true,
                'hora_devolucion_acordada' => '17:00:00',
                'ubicacion_destino_id' => $this->laboratorio->id,
            ],
            items: [
                ['tipo' => 'equipo', 'id' => $equipoReservado->id, 'cantidad' => 1],
            ]
        );

        // Asegurar que no se creó ninguna solicitud ni entrega (Rollback)
        $this->assertDatabaseEmpty('solicitudes_material');
        $this->assertDatabaseEmpty('entregas');
        $this->assertEquals('disponible', $equipoReservado->fresh()->estado);
    }

    /**
     * Prueba: Interfaz Livewire de Despacho permite atención completa en ventanilla y refleja entrega en activos.
     */
    public function test_interfaz_livewire_despacho_permite_atencion_completa_en_meson(): void
    {
        $this->actingAs($this->panolero);

        $equipo1 = Equipo::create([
            'codigo_barras' => 'EQ-MEC-PRES-005',
            'nombre' => 'Pistola Estroboscópica Digital',
            'marca' => 'Innova',
            'modelo' => '5568',
            'numero_serie' => 'SN-INN-005',
            'estado' => 'disponible',
            'ubicacion_id' => $this->panol->id,
            'reservado_clases' => false,
        ]);

        // Segundo equipo para que quede >= 1 en disponibilidad
        Equipo::create([
            'codigo_barras' => 'EQ-MEC-PRES-006',
            'nombre' => 'Pistola Estroboscópica Digital',
            'marca' => 'Innova',
            'modelo' => '5568',
            'numero_serie' => 'SN-INN-006',
            'estado' => 'disponible',
            'ubicacion_id' => $this->panol->id,
            'reservado_clases' => false,
        ]);

        Volt::test('pages.despacho.index')
            ->call('abrirModalPresencial')
            ->set('presencialNombre', 'Estudiante Matías Morales')
            ->set('presencialRut', '20.123.456-7')
            ->set('presencialCredencialVerificada', true)
            ->set('presencialHoraDevolucion', '16:00')
            ->call('agregarItemPresencial', 'equipo', $equipo1->id)
            ->call('confirmarPrestamoPresencial')
            ->assertHasNoErrors()
            ->assertSee('¡Préstamo presencial despachado exitosamente');

        // Verificar que la entrega existe en la base de datos
        $this->assertDatabaseHas('entregas', [
            'receptor_nombre' => 'Estudiante Matías Morales',
            'receptor_credencial_rut' => '20.123.456-7',
            'credencial_verificada' => true,
        ]);

        $this->assertEquals('prestado', $equipo1->fresh()->estado);
    }

    /**
     * Prueba: Ciclo de vida completo: préstamo presencial en mesón y posterior devolución física en ventanilla.
     */
    public function test_devolucion_de_prestamo_presencial_en_meson_reincorpora_material_al_stock(): void
    {
        $equipo = Equipo::create([
            'codigo_barras' => 'EQ-MEC-PRES-007',
            'nombre' => 'Manómetro de Presión de Combustible',
            'marca' => 'Actron',
            'modelo' => 'CP7838',
            'numero_serie' => 'SN-ACT-007',
            'estado' => 'disponible',
            'ubicacion_id' => $this->panol->id,
            'reservado_clases' => false,
        ]);

        Equipo::create([
            'codigo_barras' => 'EQ-MEC-PRES-008',
            'nombre' => 'Manómetro de Presión de Combustible',
            'marca' => 'Actron',
            'modelo' => 'CP7838',
            'numero_serie' => 'SN-ACT-008',
            'estado' => 'disponible',
            'ubicacion_id' => $this->panol->id,
            'reservado_clases' => false,
        ]);

        $entrega = $this->prestamoService->registrarPrestamoPresencialEnMeson(
            panolero: $this->panolero,
            datosEstudiante: [
                'nombre' => 'Estudiante Daniel Reyes',
                'rut' => '19.654.321-0',
                'credencial_verificada' => true,
                'hora_devolucion_acordada' => '18:00:00',
            ],
            items: [
                ['tipo' => 'equipo', 'id' => $equipo->id, 'cantidad' => 1],
            ]
        );

        $this->assertEquals('prestado', $equipo->fresh()->estado);

        // Simular devolución en mesón
        $detalle = $entrega->solicitud->detalles->first();
        $this->prestamoService->registrarDevolucion(
            entrega: $entrega,
            recibidor: $this->panolero,
            itemsDevolucion: [
                [
                    'detalle_id' => $detalle->id,
                    'cantidad' => 1.0,
                    'conforme' => true,
                ],
            ],
            observacionesGenerales: 'Material devuelto en mesón conforme y en óptimo estado.'
        );

        $this->assertEquals('disponible', $equipo->fresh()->estado);
        $this->assertEquals('cerrada', $entrega->solicitud->fresh()->estado);
    }
}
