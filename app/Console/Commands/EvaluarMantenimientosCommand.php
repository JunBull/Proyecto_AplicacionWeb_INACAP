<?php

namespace App\Console\Commands;

use App\Services\MantenimientoService;
use Illuminate\Console\Command;

class EvaluarMantenimientosCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'panol:evaluar-mantenimientos {--fecha= : Fecha de referencia YYYY-MM-DD para simulación}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Evalúa fechas de mantenimiento programado y genera alertas preventivas y de vencimiento según criticidad (CP-05)';

    /**
     * Execute the console command.
     */
    public function handle(MantenimientoService $servicio): int
    {
        $fechaParam = $this->option('fecha');
        $fecha = $fechaParam ? \Carbon\Carbon::parse($fechaParam) : now();

        $this->info("Evaluando mantenimientos a la fecha: {$fecha->toDateString()}...");

        $alertas = $servicio->evaluarAlertasMantenimiento($fecha);

        if (empty($alertas)) {
            $this->info('No existen mantenimientos vencidos ni en periodo de alerta preventiva.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID Mant.', 'Equipo', 'Criticidad', 'Fecha Prog.', 'Días Rest.', 'Alerta'],
            array_map(fn ($a) => [
                $a['mantenimiento_id'],
                $a['equipo_nombre'],
                strtoupper($a['criticidad']),
                $a['fecha_programada'],
                $a['dias_restantes'],
                $a['tipo_alerta'] === 'vencida' ? 'VENCIDO' : 'PREVENTIVA',
            ], $alertas)
        );

        $this->warn(sprintf('Se detectaron %d alerta(s) de mantenimiento en el sistema.', count($alertas)));

        return self::SUCCESS;
    }
}
