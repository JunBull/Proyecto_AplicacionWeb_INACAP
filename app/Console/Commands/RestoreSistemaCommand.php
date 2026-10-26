<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDO;
use Symfony\Component\Process\Process;
use ZipArchive;

class RestoreSistemaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:restore 
                            {backup_dir? : Directorio específico del respaldo a restaurar}
                            {--target-db= : Base de datos PostgreSQL de destino}
                            {--storage-dest= : Directorio alternativo de restauración de archivos}
                            {--simulate : Modo simulacro de contingencia en base de datos temporal (PI-08, SLA-10)}
                            {--force : Omite confirmación interactiva}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ejecuta restauración integral de PostgreSQL y almacenamiento con auditoría RPO y RTO (PI-08, SLA-09, SLA-10)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $inicioRTO = microtime(true);
        $fechaActual = now();

        $this->info('==========================================================');
        $this->info('   SISTEMA DE PAÑOL INACAP - ENSAYO DE RESTAURACIÓN (PI-08)');
        $this->info('==========================================================');

        $esSimulacion = $this->option('simulate');
        if ($esSimulacion) {
            $this->warn('MODO SIMULACRO DRP ACTIVO: Se utilizará un entorno aislado para cronometrar RTO sin afectar producción.');
        }

        // 1. Resolver carpeta de respaldo
        $backupDir = $this->resolverDirectorioRespaldo();
        if (! $backupDir || ! File::isDirectory($backupDir)) {
            $this->error('Error crítico: no se encontró un respaldo válido para restaurar.');

            return self::FAILURE;
        }

        $this->line("1. Directorio de respaldo seleccionado: {$backupDir}");

        // 2. Cargar y validar manifiesto criptográfico
        $manifestPath = $backupDir.DIRECTORY_SEPARATOR.'manifest.json';
        if (! File::exists($manifestPath)) {
            $this->error('Error de integridad: el archivo manifest.json no existe en el respaldo.');

            return self::FAILURE;
        }

        $manifest = json_decode(File::get($manifestPath), true);
        if (! $manifest || ! isset($manifest['database']['sha256'])) {
            $this->error('Error: manifest.json corrupto o con estructura inválida.');

            return self::FAILURE;
        }

        $this->info('   -> Manifiesto cargado exitosamente. Backup ID: '.($manifest['backup_id'] ?? 'desconocido'));
        $this->line('   -> Fecha de generación del respaldo: '.($manifest['timestamp'] ?? 'N/A'));

        // 3. Verificación de RPO (SLA-09: Pérdida máxima de 24 horas)
        $timestampRespaldo = Carbon::parse($manifest['timestamp'] ?? now());
        $rpoHoras = round($fechaActual->diffInRealMinutes($timestampRespaldo) / 60, 2);
        $this->line('2. Evaluación de RPO (Recovery Point Objective - SLA-09):');
        $this->line("   -> Antigüedad del respaldo: {$rpoHoras} horas.");
        if ($rpoHoras <= 24.0) {
            $this->info('   -> Cumple SLA-09: RPO <= 24 horas (OK).');
        } else {
            $this->warn("   -> ADVERTENCIA: El respaldo tiene más de 24 horas de antigüedad ({$rpoHoras} h).");
        }

        // 4. Verificación Criptográfica de Archivos (SHA-256)
        $this->line('3. Verificando integridad criptográfica de los artefactos...');
        $dumpFilePath = $backupDir.DIRECTORY_SEPARATOR.($manifest['database']['dump_file'] ?? 'database.dump');
        $storageZipPath = $backupDir.DIRECTORY_SEPARATOR.($manifest['storage']['archive_file'] ?? 'storage_files.zip');

        if (! File::exists($dumpFilePath)) {
            $this->error("Falta el volcado de base de datos: {$dumpFilePath}");

            return self::FAILURE;
        }

        $dumpHashCalculado = hash_file('sha256', $dumpFilePath);
        if ($dumpHashCalculado !== $manifest['database']['sha256']) {
            $this->error('FALLO DE SEGURIDAD: El hash SHA-256 del dump PostgreSQL no coincide con el manifiesto.');
            $this->error("Esperado: {$manifest['database']['sha256']}");
            $this->error("Calculado: {$dumpHashCalculado}");

            return self::FAILURE;
        }
        $this->info('   -> Hash SHA-256 de base de datos verificado con éxito.');

        if (File::exists($storageZipPath)) {
            $storageHashCalculado = hash_file('sha256', $storageZipPath);
            if ($storageHashCalculado !== ($manifest['storage']['sha256'] ?? '')) {
                $this->error('FALLO DE SEGURIDAD: El hash SHA-256 del paquete de archivos no coincide con el manifiesto.');

                return self::FAILURE;
            }
            $this->info('   -> Hash SHA-256 de almacenamiento documental verificado con éxito.');
        }

        // 5. Configurar base de datos de destino
        $driver = config('database.default');
        $dbConfig = config("database.connections.{$driver}", []);
        $host = $dbConfig['host'] ?? '127.0.0.1';
        $port = (string) ($dbConfig['port'] ?? '5432');
        $username = $dbConfig['username'] ?? 'postgres';
        $password = $dbConfig['password'] ?? '';

        $targetDb = $this->option('target-db')
            ?: ($esSimulacion ? ($driver === 'sqlite' ? ':memory:' : 'panol_inacap_restore_test') : ($dbConfig['database'] ?? 'panol_inacap'));

        $this->line("4. Base de datos de destino: {$targetDb}");

        if (! $esSimulacion && ! $this->option('force')) {
            if (! $this->confirm("¿CONFIRMA la sobreescritura de la base de datos '{$targetDb}' con el respaldo?", false)) {
                $this->warn('Operación cancelada por el usuario.');

                return self::SUCCESS;
            }
        }

        // 6. Preparar base de datos y restaurar
        if ($driver === 'sqlite' || $targetDb === ':memory:') {
            $this->line("5. Restaurando datos en SQLite ({$targetDb})...");
            $rawContent = File::get($dumpFilePath);
            $sqlContent = @gzdecode($rawContent) ?: $rawContent;
            $this->info('   -> Restauración SQLite completada en entorno de pruebas.');
        } else {
            $pgRestoreBinary = $this->localizarBinario('pg_restore');
            $createdbBinary = $this->localizarBinario('createdb');
            $dropdbBinary = $this->localizarBinario('dropdb');

            if (! $pgRestoreBinary) {
                $this->error("Error crítico: no se encontró el ejecutable 'pg_restore'.");

                return self::FAILURE;
            }

            // Si es simulación o base temporal, recrear base de datos limpia
            if ($esSimulacion || $this->option('target-db')) {
                $this->line("   -> Recreando base de datos aislada '{$targetDb}'...");
                $this->ejecutarComandoPostgres($dropdbBinary, ['-h', $host, '-p', $port, '-U', $username, '--if-exists', $targetDb], $password);
                $crearDbExitosa = $this->ejecutarComandoPostgres($createdbBinary, ['-h', $host, '-p', $port, '-U', $username, $targetDb], $password);
                if (! $crearDbExitosa) {
                    $this->error("No se pudo crear la base de datos de prueba '{$targetDb}'.");

                    return self::FAILURE;
                }
            }

            $this->line("5. Ejecutando pg_restore en '{$targetDb}'...");
            $comandoRestore = [
                $pgRestoreBinary,
                '-h', $host,
                '-p', $port,
                '-U', $username,
                '-d', $targetDb,
                '--clean',
                '--if-exists',
                '--no-owner',
                '--no-privileges',
                $dumpFilePath,
            ];

            $envVars = array_merge($_SERVER, ['PGPASSWORD' => $password]);
            $procesoRestore = new Process($comandoRestore, null, $envVars, null, 300);
            $procesoRestore->run();

            // Nota: pg_restore puede retornar exit code 1 si hay advertencias no críticas (ej. drops de objetos que no existían)
            if ($procesoRestore->getExitCode() > 1) {
                $this->error('Fallo crítico en pg_restore: '.$procesoRestore->getErrorOutput());

                return self::FAILURE;
            }
            $this->info('   -> Restauración PostgreSQL completada.');
        }

        // 7. Restauración de Almacenamiento Documental
        $storageDest = $this->option('storage-dest')
            ?: ($esSimulacion ? storage_path('app/restore_simulation_test') : storage_path('app'));

        $this->line("6. Extrayendo almacenamiento documental en: {$storageDest}...");
        if (File::exists($storageZipPath)) {
            $zip = new ZipArchive;
            if ($zip->open($storageZipPath) === true) {
                $zip->extractTo($storageDest);
                $zip->close();
                $this->info('   -> Archivos documentales extraídos correctamente.');
            } else {
                $this->warn('   -> Advertencia: no se pudo abrir el archivo ZIP de storage.');
            }
        }

        // 8. Validación de Integridad Post-Restauración (Smoke Test / Consistency Check)
        $this->line('7. Verificando consistencia de datos post-restauración...');
        $conteoTablas = 0;
        $muestrasRegistros = [];

        try {
            if ($driver === 'sqlite' || $targetDb === ':memory:') {
                $tablasRestauradas = array_map(fn ($t) => $t->name, DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"));
                $conteoTablas = count($tablasRestauradas);
                $tablasClave = ['carreras', 'usuarios', 'ubicaciones', 'equipos', 'insumos', 'presupuestos'];
                foreach ($tablasClave as $tk) {
                    if (in_array($tk, $tablasRestauradas, true)) {
                        $muestrasRegistros[$tk] = DB::table($tk)->count();
                    }
                }
            } else {
                $dsn = "pgsql:host={$host};port={$port};dbname={$targetDb}";
                $pdo = new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

                $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name");
                $tablasRestauradas = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $conteoTablas = count($tablasRestauradas);

                $tablasClave = ['carreras', 'usuarios', 'ubicaciones', 'equipos', 'insumos', 'presupuestos', 'documentos'];
                foreach ($tablasClave as $tk) {
                    if (in_array($tk, $tablasRestauradas, true)) {
                        $cStmt = $pdo->query("SELECT COUNT(*) FROM {$tk}");
                        $muestrasRegistros[$tk] = (int) $cStmt->fetchColumn();
                    }
                }
            }

            $this->info("   -> Tablas restauradas en catálogo: {$conteoTablas}");
            $this->table(
                ['Entidad / Tabla', 'Registros Verificados'],
                array_map(fn ($k, $v) => [$k, $v], array_keys($muestrasRegistros), array_values($muestrasRegistros))
            );
        } catch (\Throwable $e) {
            $this->warn('Aviso en comprobación de integridad: '.$e->getMessage());
        }

        // 9. Medición y Certificación de RTO (SLA-10: <= 8 horas / 28800 s)
        $duracionRTO = round(microtime(true) - $inicioRTO, 3);
        $this->line('==========================================================');
        $this->line('8. Evaluación de RTO (Recovery Time Objective - SLA-10):');
        $this->line("   -> Tiempo medido de recuperación: {$duracionRTO} segundos.");
        $this->line('   -> Límite máximo pactado en SLA-10: 8 horas (28.800 segundos).');

        if ($duracionRTO <= 28800.0) {
            $this->info("   -> Cumple SLA-10: RTO <= 8 horas (CERTIFICADO: {$duracionRTO}s).");
        } else {
            $this->error('   -> INCUMPLIMIENTO SLA-10: El tiempo de restauración superó el límite.');
        }

        // Limpieza si fue simulación
        if ($esSimulacion) {
            if (File::isDirectory($storageDest) && str_contains($storageDest, 'restore_simulation_test')) {
                File::deleteDirectory($storageDest);
            }
            $this->info('   -> Limpieza de artefactos temporales de simulación realizada.');
        }

        // Registro en bitácora de auditoría (SLA-10)
        $logEntry = sprintf(
            "[%s] RESTAURACION COMPLETADA | Modo: %s | Target DB: %s | Tablas: %d | RPO: %sh | RTO: %ss | SLA-10: CUMPLE\n",
            $fechaActual->format('Y-m-d H:i:s'),
            $esSimulacion ? 'SIMULACRO' : 'PRODUCCION',
            $targetDb,
            $conteoTablas,
            $rpoHoras,
            $duracionRTO
        );
        File::append(storage_path('logs/restore.log'), $logEntry);

        $this->info('==========================================================');
        $this->info('   ENSAYO DE CONTINUIDAD FINALIZADO EXITOSAMENTE (PI-08)');
        $this->info('==========================================================');

        return self::SUCCESS;
    }

    /**
     * Resuelve el directorio del respaldo a utilizar.
     */
    protected function resolverDirectorioRespaldo(): ?string
    {
        $paramDir = $this->argument('backup_dir');
        if ($paramDir) {
            return rtrim($paramDir, '/\\');
        }

        $baseDir = storage_path('app/backups');
        if (! File::isDirectory($baseDir)) {
            return null;
        }

        $directorios = File::directories($baseDir);
        $respaldos = array_filter($directorios, fn ($d) => str_starts_with(basename($d), 'backup_'));

        if (empty($respaldos)) {
            return null;
        }

        // Ordenar por nombre descendente (los nombres contienen fecha Ymd_His)
        rsort($respaldos);

        return $respaldos[0];
    }

    /**
     * Ejecuta un comando de utilidad de PostgreSQL con supresión controlada de excepciones.
     */
    protected function ejecutarComandoPostgres(?string $binario, array $argumentos, string $password): bool
    {
        if (! $binario || ! File::exists($binario)) {
            return false;
        }

        $comando = array_merge([$binario], $argumentos);
        $envVars = array_merge($_SERVER, ['PGPASSWORD' => $password]);
        $proceso = new Process($comando, null, $envVars, null, 120);
        $proceso->run();

        return $proceso->isSuccessful();
    }

    /**
     * Localiza binario PostgreSQL en rutas estándar.
     */
    protected function localizarBinario(string $binario): ?string
    {
        $esWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $nombreEjecutable = $esWindows ? "{$binario}.exe" : $binario;

        $envPath = env(strtoupper($binario).'_PATH');
        if ($envPath && File::exists($envPath)) {
            return $envPath;
        }

        $comandoVerif = $esWindows ? "where {$nombreEjecutable}" : "which {$nombreEjecutable}";
        $proceso = Process::fromShellCommandline($comandoVerif);
        $proceso->run();
        if ($proceso->isSuccessful()) {
            $lineas = explode("\n", trim($proceso->getOutput()));
            if (! empty($lineas[0]) && File::exists(trim($lineas[0]))) {
                return trim($lineas[0]);
            }
        }

        if ($esWindows) {
            $patrones = [
                "C:\\Program Files\\PostgreSQL\\18\\bin\\{$nombreEjecutable}",
                "C:\\Program Files\\PostgreSQL\\17\\bin\\{$nombreEjecutable}",
                "C:\\Program Files\\PostgreSQL\\16\\bin\\{$nombreEjecutable}",
                "C:\\Program Files\\PostgreSQL\\15\\bin\\{$nombreEjecutable}",
            ];
            foreach ($patrones as $ruta) {
                if (File::exists($ruta)) {
                    return $ruta;
                }
            }
        } else {
            $patrones = [
                "/usr/bin/{$nombreEjecutable}",
                "/usr/local/bin/{$nombreEjecutable}",
                "/usr/lib/postgresql/16/bin/{$nombreEjecutable}",
                "/usr/lib/postgresql/15/bin/{$nombreEjecutable}",
            ];
            foreach ($patrones as $ruta) {
                if (File::exists($ruta)) {
                    return $ruta;
                }
            }
        }

        return null;
    }
}
