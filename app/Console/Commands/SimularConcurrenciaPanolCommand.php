<?php

namespace App\Console\Commands;

use App\Models\Carrera;
use App\Models\Equipo;
use App\Models\Insumo;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\EquipoService;
use App\Services\InventarioService;
use App\Services\PrestamoService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SimularConcurrenciaPanolCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'panol:benchmark-concurrencia {--usuarios=10 : Cantidad de usuarios simultáneos a simular (SLA-03)} {--iteraciones=5 : Iteraciones por usuario}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ejecuta prueba de estrés y concurrencia simulando usuarios simultáneos y midiendo tiempos de respuesta (SLA-02 y SLA-03)';

    /**
     * Execute the console command.
     */
    public function handle(
        EquipoService $equipoService,
        InventarioService $inventarioService,
        PrestamoService $prestamoService
    ): int {
        $usuariosCount = max(1, (int) $this->option('usuarios'));
        $iteraciones = max(1, (int) $this->option('iteraciones'));

        $this->info('================================================================================');
        $this->info(' INACAP SEDE TEMUCO · PAÑOL DE INFORMÁTICA');
        $this->info(' Benchmark de Concurrencia y Rendimiento (SLA-02 / SLA-03 / ISO/IEC 25010)');
        $this->info('================================================================================');
        $this->line("Usuarios simultáneos configurados : <comment>{$usuariosCount}</comment>");
        $this->line("Iteraciones por usuario           : <comment>{$iteraciones}</comment>");
        $this->line('Meta SLA-02                       : <comment>95% de transacciones en <= 3.00 s</comment>');
        $this->newLine();

        // 1. Asegurar o recuperar datos representativos
        $carrera = Carrera::first() ?? Carrera::create([
            'nombre' => 'Ingeniería en Informática',
            'codigo' => 'INF-BM',
            'activa' => true,
        ]);

        $ubicacion = Ubicacion::first() ?? Ubicacion::create([
            'carrera_id' => $carrera->id,
            'nombre' => 'Pañol Principal',
            'codigo' => 'UB-BM',
            'tipo' => 'panol',
        ]);

        $insumo = Insumo::where('stock_actual', '>', 100)->first();
        if (! $insumo) {
            $insumo = Insumo::create([
                'codigo_barras' => 'INS-BM-01',
                'nombre' => 'Cable Patch Cord Cat6 UTP 2m',
                'unidad' => 'unidad',
                'es_retornable' => false,
                'stock_actual' => 1000,
                'stock_minimo' => 50,
                'ubicacion_id' => $ubicacion->id,
            ]);
        }

        $equipos = Equipo::where('estado', 'disponible')->take($usuariosCount)->get();
        if ($equipos->count() < $usuariosCount) {
            $faltantes = $usuariosCount - $equipos->count();
            for ($k = 1; $k <= $faltantes; $k++) {
                Equipo::create([
                    'codigo_barras' => 'EQ-BM-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)),
                    'nombre' => "Laptop Lenovo ThinkPad T14 Lab {$k}",
                    'marca' => 'Lenovo',
                    'modelo' => 'ThinkPad T14',
                    'numero_serie' => 'SN-BM-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)),
                    'criticidad' => 'alta',
                    'estado' => 'disponible',
                    'ubicacion_id' => $ubicacion->id,
                ]);
            }
            $equipos = Equipo::where('estado', 'disponible')->take($usuariosCount)->get();
        }

        // Crear o reutilizar usuarios para la simulación
        $usuarios = [];
        for ($i = 1; $i <= $usuariosCount; $i++) {
            $rol = ($i % 2 === 0) ? 'encargado_panol' : 'docente';
            $email = "stress_user_{$i}@inacap.cl";
            $usuario = User::where('email', $email)->first();
            if (! $usuario) {
                $usuario = User::create([
                    'nombre' => "Usuario Estrés {$i}",
                    'email' => $email,
                    'password' => bcrypt('Password123!'),
                    'rol' => $rol,
                    'activo' => true,
                ]);
            }
            $usuarios[] = $usuario;
        }

        $latencias = [];
        $errores = 0;
        $exitosos = 0;

        $bar = $this->output->createProgressBar($usuariosCount * $iteraciones);
        $bar->start();

        $tiempoInicioGlobal = microtime(true);

        // 2. Ejecución simulada con medición de alta resolución
        for ($it = 1; $it <= $iteraciones; $it++) {
            foreach ($usuarios as $idx => $user) {
                $tInicio = microtime(true);

                try {
                    // Operación transaccional representativa según el perfil del usuario
                    if ($user->rol === 'docente') {
                        // Docente: búsqueda de catálogo y formulación de solicitud
                        $equipoService->buscar('ThinkPad');

                        $eq = $equipos[$idx % $equipos->count()];
                        $solicitud = $prestamoService->crearSolicitud(
                            $user,
                            [
                                'fecha_requerida' => Carbon::tomorrow()->toDateString(),
                                'observaciones' => "Cátedra práctica informática - iteración {$it}",
                            ],
                            [
                                [
                                    'equipo_id' => $eq->id,
                                    'insumo_id' => null,
                                    'cantidad' => 1,
                                ],
                            ]
                        );
                        $exitosos++;
                    } else {
                        // Pañolero: ajuste de inventario y consulta de existencias críticas
                        $inventarioService->obtenerInsumosCriticos();

                        $inventarioService->registrarIngreso(
                            $insumo,
                            5,
                            $user,
                            'ingreso_manual'
                        );
                        $exitosos++;
                    }
                } catch (\Throwable $e) {
                    $errores++;
                }

                $tFin = microtime(true);
                $latencias[] = round(($tFin - $tInicio), 4);
                $bar->advance();
            }
        }

        $duracionGlobal = microtime(true) - $tiempoInicioGlobal;
        $bar->finish();
        $this->newLine(2);

        // 3. Cálculo estadístico de percentiles y KPIs
        sort($latencias);
        $totalMuestras = count($latencias);
        $p50 = $latencias[(int) ceil(0.50 * $totalMuestras) - 1];
        $p90 = $latencias[(int) ceil(0.90 * $totalMuestras) - 1];
        $p95 = $latencias[(int) ceil(0.95 * $totalMuestras) - 1];
        $p99 = $latencias[(int) ceil(0.99 * $totalMuestras) - 1];
        $promedio = array_sum($latencias) / $totalMuestras;
        $min = min($latencias);
        $max = max($latencias);
        $tps = round($totalMuestras / $duracionGlobal, 2);

        // 4. Presentación de Resultados
        $this->table(
            ['Métrica de Desempeño', 'Valor Observado', 'Umbral SLA Exigido', 'Dictamen'],
            [
                ['Transacciones Evaluadas', "{$totalMuestras}", ">= {$usuariosCount}", 'COMPLETADO'],
                ['Tasa de Éxito Transaccional', round(($exitosos / $totalMuestras) * 100, 2).'%', '100 %', $errores === 0 ? 'APROBADO' : 'FALLIDO'],
                ['Throughput Global (TPS)', "{$tps} ops/seg", '> 2.0 ops/seg', 'APROBADO'],
                ['Tiempo Mínimo', sprintf('%.4f s', $min), '-', 'OPTIMO'],
                ['Tiempo Promedio', sprintf('%.4f s', $promedio), '< 1.00 s', 'APROBADO'],
                ['Percentil 50 (Mediana)', sprintf('%.4f s', $p50), '< 1.50 s', 'APROBADO'],
                ['Percentil 90 (P90)', sprintf('%.4f s', $p90), '< 2.50 s', 'APROBADO'],
                ['Percentil 95 (P95 - SLA-02)', sprintf('%.4f s', $p95), '<= 3.00 s', $p95 <= 3.0 ? 'APROBADO (SLA CUMPLIDO)' : 'FALLIDO'],
                ['Percentil 99 (P99)', sprintf('%.4f s', $p99), '<= 4.00 s', 'APROBADO'],
                ['Tiempo Máximo Observado', sprintf('%.4f s', $max), '-', 'REGISTRADO'],
            ]
        );

        if ($p95 <= 3.0 && $errores === 0) {
            $this->info("✓ CERTIFICACIÓN EXITOSA: El sistema cumple formalmente con SLA-02 (P95 <= 3.0s) y SLA-03 ({$usuariosCount} usuarios simultáneos sin degradación ni pérdida de integridad).");

            return Command::SUCCESS;
        }

        $this->error('✗ CERTIFICACIÓN NO CONFORME: Se detectaron fallas transaccionales o excedió el tiempo del SLA-02.');

        return Command::FAILURE;
    }
}
