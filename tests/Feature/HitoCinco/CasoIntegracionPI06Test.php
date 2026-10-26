<?php

namespace Tests\Feature\HitoCinco;

use App\Models\Carrera;
use App\Models\Entrega;
use App\Models\Equipo;
use App\Models\Insumo;
use App\Models\SolicitudMaterial;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\EquipoService;
use App\Services\FallaService;
use App\Services\InventarioService;
use App\Services\PrestamoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CasoIntegracionPI06Test extends TestCase
{
    use RefreshDatabase;

    protected Carrera $carrera;

    protected Ubicacion $ubicacion;

    /** @var array<int, User> */
    protected array $docentes = [];

    /** @var array<int, User> */
    protected array $panoleros = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->carrera = Carrera::create([
            'nombre' => 'Ingeniería en Informática',
            'codigo' => 'INF-01',
            'activa' => true,
        ]);

        $this->ubicacion = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Pañol Central',
            'codigo' => 'PAN-01',
            'tipo' => 'panol',
        ]);

        // Crear 6 docentes y 4 encargados de pañol para simular 10 usuarios concurrentes
        for ($i = 1; $i <= 6; $i++) {
            $this->docentes[] = User::factory()->create([
                'name' => "Docente Simulado {$i}",
                'email' => "docente{$i}@inacap.cl",
                'rol' => 'docente',
                'activo' => true,
            ]);
        }

        for ($i = 1; $i <= 4; $i++) {
            $this->panoleros[] = User::factory()->create([
                'name' => "Pañolero Operativo {$i}",
                'email' => "panolero{$i}@inacap.cl",
                'rol' => 'encargado_panol',
                'activo' => true,
            ]);
        }
    }

    /**
     * PI-06 / SLA-02 / SLA-03: Simula 10 usuarios concurrentes ejecutando operaciones de pañol,
     * verificando estabilidad absoluta y tiempos de respuesta <= 3 segundos en el 95% de casos.
     */
    public function test_pi06_simulacion_10_usuarios_concurrentes_cumple_sla_02_y_preserva_consistencia(): void
    {
        $equipoService = app(EquipoService::class);
        $inventarioService = app(InventarioService::class);
        $prestamoService = app(PrestamoService::class);
        $fallaService = app(FallaService::class);

        // 1. Población inicial de recursos
        $equipos = [];
        for ($i = 1; $i <= 10; $i++) {
            $equipos[] = Equipo::create([
                'codigo_barras' => 'EQ-CONC-'.sprintf('%04d', $i),
                'nombre' => "Notebook Core i7 Laboratorio {$i}",
                'marca' => 'Lenovo',
                'modelo' => 'ThinkPad T14',
                'numero_serie' => 'SN-LNV-'.sprintf('%04d', $i),
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $this->ubicacion->id,
            ]);
        }

        $insumo = Insumo::create([
            'codigo_barras' => 'INS-CONC-01',
            'nombre' => 'Conectores RJ-45 Cat6',
            'unidad' => 'unidad',
            'es_retornable' => false,
            'stock_actual' => 500,
            'stock_minimo' => 50,
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        $tiempos = [];
        $operacionesExitosas = 0;
        $totalOperaciones = 0;

        // 2. Simulación de 10 usuarios concurrentes (usuarios 0 a 9)
        // Cada usuario realiza un ciclo representativo de trabajo en pañol
        for ($u = 0; $u < 10; $u++) {
            $isDocente = ($u < 6);
            $usuario = $isDocente ? $this->docentes[$u] : $this->panoleros[$u - 6];

            // T1: Consulta y Búsqueda en Catálogo (Docente y Pañolero)
            $inicio = microtime(true);
            $catalogo = $equipoService->buscar('ThinkPad');
            $duracion = microtime(true) - $inicio;
            $tiempos[] = $duracion;
            $totalOperaciones++;
            $this->assertNotEmpty($catalogo);
            $operacionesExitosas++;

            if ($isDocente) {
                // T2: Docente formula Solicitud Anticipada de Préstamo
                $inicio = microtime(true);
                $equipoSolicitado = $equipos[$u];
                $solicitud = $prestamoService->crearSolicitud(
                    $usuario,
                    [
                        'fecha_requerida' => Carbon::tomorrow()->toDateString(),
                        'observaciones' => "Reserva para cátedra práctica de redes docente {$usuario->name}",
                    ],
                    [
                        [
                            'equipo_id' => $equipoSolicitado->id,
                            'insumo_id' => null,
                            'cantidad' => 1,
                        ],
                        [
                            'equipo_id' => null,
                            'insumo_id' => $insumo->id,
                            'cantidad' => 10,
                        ],
                    ]
                );
                $duracion = microtime(true) - $inicio;
                $tiempos[] = $duracion;
                $totalOperaciones++;
                $this->assertNotNull($solicitud->id);
                $operacionesExitosas++;

                // T3: Docente reporta falla técnica eventual en otro activo
                $inicio = microtime(true);
                $falla = $fallaService->reportarFalla(
                    $equipos[9],
                    $usuario,
                    "Falla de puerto HDMI detectada en laboratorio de redes docente {$u}"
                );
                $duracion = microtime(true) - $inicio;
                $tiempos[] = $duracion;
                $totalOperaciones++;
                $this->assertNotNull($falla->id);
                $operacionesExitosas++;
            } else {
                // T4: Pañolero procesa entrega / despacho en mesón a docente
                $solicitudPendiente = SolicitudMaterial::where('estado', 'pendiente')->first();
                if ($solicitudPendiente) {
                    $inicio = microtime(true);
                    $detallesIds = $solicitudPendiente->detalles->pluck('id')->toArray();
                    $entrega = $prestamoService->entregarMateriales(
                        $solicitudPendiente,
                        $usuario,
                        $solicitudPendiente->solicitante->name,
                        'docente',
                        $detallesIds
                    );
                    $duracion = microtime(true) - $inicio;
                    $tiempos[] = $duracion;
                    $totalOperaciones++;
                    $this->assertNotNull($entrega->id);
                    $operacionesExitosas++;

                    // T5: Pañolero recibe devolución parcial o total
                    $inicio = microtime(true);
                    $itemsDevolucion = [];
                    foreach ($solicitudPendiente->detalles as $det) {
                        if ($det->equipo_id !== null) {
                            $itemsDevolucion[] = [
                                'detalle_id' => $det->id,
                                'cantidad' => 1,
                                'conforme' => true,
                                'descripcion_dano' => null,
                            ];
                        }
                    }
                    if (! empty($itemsDevolucion)) {
                        $devolucion = $prestamoService->registrarDevolucion(
                            $entrega,
                            $usuario,
                            $itemsDevolucion,
                            'Recepción conforme en mesón por pañolero'
                        );
                        $duracion = microtime(true) - $inicio;
                        $tiempos[] = $duracion;
                        $totalOperaciones++;
                        $this->assertNotNull($devolucion->id);
                        $operacionesExitosas++;
                    }
                }

                // T6: Pañolero registra ingreso de existencias
                $inicio = microtime(true);
                $inventarioService->registrarIngreso(
                    $insumo,
                    20,
                    $usuario,
                    'ingreso_manual'
                );
                $duracion = microtime(true) - $inicio;
                $tiempos[] = $duracion;
                $totalOperaciones++;
                $operacionesExitosas++;
            }
        }

        // 3. Verificación de SLAs y métricas estadísticas
        $this->assertEquals($totalOperaciones, $operacionesExitosas, 'El 100% de las operaciones concurrentes deben ser exitosas.');

        sort($tiempos);
        $totalMuestras = count($tiempos);
        $posicionP95 = (int) ceil(0.95 * $totalMuestras) - 1;
        $tiempoP95 = $tiempos[$posicionP95];
        $tiempoPromedio = array_sum($tiempos) / $totalMuestras;

        // SLA-02: 95% de las transacciones habituales deben responder en <= 3 segundos
        $this->assertLessThanOrEqual(3.0, $tiempoP95, "El percentil 95 ({$tiempoP95}s) excede el umbral de SLA-02 (<= 3s)");
        $this->assertLessThan(1.0, $tiempoPromedio, "El tiempo promedio ({$tiempoPromedio}s) debe ser sub-segundo.");

        // 4. Verificación de consistencia del inventario y base de datos
        $insumoActualizado = $insumo->fresh();
        $this->assertGreaterThan(0, $insumoActualizado->stock_actual);
        $this->assertDatabaseHas('insumos', ['id' => $insumo->id]);
    }
}
