<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use ZipArchive;

class BackupSistemaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:backup 
                            {--destination= : Directorio alternativo de almacenamiento}
                            {--keep-days=30 : Días de retención para rotación de respaldos (SLA-08)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ejecuta respaldo integral diario de PostgreSQL y almacenamiento documental (SLA-08, SLA-09)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $inicio = microtime(true);
        $fechaActual = now();
        $timestamp = $fechaActual->format('Ymd_His');
        $backupName = 'backup_'.$timestamp;

        $baseDir = $this->option('destination')
            ? rtrim($this->option('destination'), '/\\')
            : storage_path('app/backups');

        $backupDir = $baseDir.DIRECTORY_SEPARATOR.$backupName;

        if (! File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $this->info('==========================================================');
        $this->info('   SISTEMA DE PAÑOL INACAP - RESPALDO AUTOMATIZADO (SLA-08)');
        $this->info('==========================================================');
        $this->info("Fecha y hora: {$fechaActual->toIso8601String()}");
        $this->info("Destino: {$backupDir}");

        // 1. Obtener configuración de conexión de base de datos
        $driver = config('database.default');
        $dbConfig = config("database.connections.{$driver}", []);
        $host = $dbConfig['host'] ?? '127.0.0.1';
        $port = (string) ($dbConfig['port'] ?? '5432');
        $database = $dbConfig['database'] ?? 'panol_inacap';
        $username = $dbConfig['username'] ?? 'postgres';
        $password = $dbConfig['password'] ?? '';

        $dumpFileName = 'database.dump';
        $dumpFilePath = $backupDir.DIRECTORY_SEPARATOR.$dumpFileName;

        $this->line("1. Generando volcado comprimido de base de datos ({$driver} -> {$database})...");

        if ($driver === 'sqlite' || $database === ':memory:') {
            // Entorno de pruebas / SQLite
            $this->generarDumpSqlite($dumpFilePath);
        } else {
            // Entorno de producción / PostgreSQL
            $pgDumpBinary = $this->localizarBinario('pg_dump');
            if (! $pgDumpBinary) {
                $this->error("Error crítico: no se encontró el ejecutable 'pg_dump' en el sistema.");

                return self::FAILURE;
            }

            // Ejecución de pg_dump con formato Custom (-F c: compresión nativa zlib)
            $comandoDump = [
                $pgDumpBinary,
                '-h', $host,
                '-p', $port,
                '-U', $username,
                '-F', 'c',
                '-b',
                '-v',
                '-f', $dumpFilePath,
                $database,
            ];

            $envVars = array_merge($_SERVER, [
                'PGPASSWORD' => $password,
            ]);

            $procesoDump = new Process($comandoDump, null, $envVars, null, 300);
            $procesoDump->run();

            if (! $procesoDump->isSuccessful()) {
                $this->error('Fallo al ejecutar pg_dump: '.$procesoDump->getErrorOutput());
                Log::channel('single')->error('app:backup - Error en pg_dump', [
                    'error' => $procesoDump->getErrorOutput(),
                ]);

                return self::FAILURE;
            }
        }

        $dumpSize = File::size($dumpFilePath);
        $dumpSha256 = hash_file('sha256', $dumpFilePath);
        $this->info('   -> Volcado de base de datos completado exitosamente: '.$this->formatearBytes($dumpSize));
        $this->line("   -> SHA-256 DB: {$dumpSha256}");

        // 2. Comprimir almacenamiento de archivos y documentos
        $this->line('2. Empaquetando almacenamiento documental (storage/app)...');
        $storageZipName = 'storage_files.zip';
        $storageZipPath = $backupDir.DIRECTORY_SEPARATOR.$storageZipName;

        $archivosEmpaquetados = $this->empaquetarArchivosStorage($storageZipPath);
        $storageSize = File::size($storageZipPath);
        $storageSha256 = hash_file('sha256', $storageZipPath);
        $this->info("   -> Almacenamiento empaquetado ({$archivosEmpaquetados} archivos): ".$this->formatearBytes($storageSize));
        $this->line("   -> SHA-256 Storage: {$storageSha256}");

        // 3. Obtener listado de tablas para auditoría en manifiesto
        $tablas = [];
        try {
            if ($driver === 'sqlite') {
                $tablasRaw = DB::select("SELECT name as table_name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
            } else {
                $tablasRaw = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name");
            }
            $tablas = array_map(fn ($t) => $t->table_name, $tablasRaw);
        } catch (\Throwable $e) {
            $this->warn('No se pudo obtener el listado de tablas vía PDO: '.$e->getMessage());
        }

        // 4. Generar Manifiesto Criptográfico de Respaldo
        $duracionSegundos = round(microtime(true) - $inicio, 3);
        $manifiesto = [
            'backup_id' => $backupName,
            'timestamp' => $fechaActual->toIso8601String(),
            'database' => [
                'name' => $database,
                'host' => $host,
                'port' => $port,
                'dump_file' => $dumpFileName,
                'size_bytes' => $dumpSize,
                'size_formatted' => $this->formatearBytes($dumpSize),
                'sha256' => $dumpSha256,
                'tables_count' => count($tablas),
                'tables' => $tablas,
            ],
            'storage' => [
                'archive_file' => $storageZipName,
                'files_count' => $archivosEmpaquetados,
                'size_bytes' => $storageSize,
                'size_formatted' => $this->formatearBytes($storageSize),
                'sha256' => $storageSha256,
            ],
            'compliance' => [
                'sla_08_retencion_dias' => (int) $this->option('keep-days'),
                'sla_09_rpo_max_horas' => 24,
                'sla_10_rto_max_horas' => 8,
            ],
            'execution_time_seconds' => $duracionSegundos,
            'status' => 'COMPLETED',
        ];

        File::put($backupDir.DIRECTORY_SEPARATOR.'manifest.json', json_encode($manifiesto, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->info('3. Manifiesto criptográfico generado (manifest.json).');

        // 5. Aplicar política de retención de 30 días (SLA-08)
        $diasRetencion = (int) $this->option('keep-days');
        $this->line("4. Evaluando política de retención ({$diasRetencion} días)...");
        $eliminados = $this->aplicarRotacionRespaldos($baseDir, $diasRetencion);
        if ($eliminados > 0) {
            $this->warn("   -> Se eliminaron {$eliminados} respaldo(s) con antigüedad mayor a {$diasRetencion} días.");
        } else {
            $this->line('   -> No existen respaldos antiguos que superen la ventana de retención.');
        }

        // 6. Registro en bitácora de auditoría de respaldos (SLA-08)
        $logEntry = sprintf(
            "[%s] RESPALDO EXITOSO | ID: %s | DB: %s (%s) | Archivos: %d (%s) | Duración: %ss\n",
            $fechaActual->format('Y-m-d H:i:s'),
            $backupName,
            $database,
            $this->formatearBytes($dumpSize),
            $archivosEmpaquetados,
            $this->formatearBytes($storageSize),
            $duracionSegundos
        );
        File::append(storage_path('logs/backup.log'), $logEntry);

        $this->info('==========================================================');
        $this->info("   RESPALDO COMPLETADO EXITOSAMENTE EN {$duracionSegundos} SEGUNDOS");
        $this->info('==========================================================');

        return self::SUCCESS;
    }

    /**
     * Localiza el binario de PostgreSQL en rutas estándar.
     */
    protected function localizarBinario(string $binario): ?string
    {
        $esWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $nombreEjecutable = $esWindows ? "{$binario}.exe" : $binario;

        // 1. Variable de entorno personalizada
        $envPath = env(strtoupper($binario).'_PATH');
        if ($envPath && File::exists($envPath)) {
            return $envPath;
        }

        // 2. Comprobar si está en el PATH del sistema
        $comandoVerif = $esWindows ? "where {$nombreEjecutable}" : "which {$nombreEjecutable}";
        $proceso = Process::fromShellCommandline($comandoVerif);
        $proceso->run();
        if ($proceso->isSuccessful()) {
            $lineas = explode("\n", trim($proceso->getOutput()));
            if (! empty($lineas[0]) && File::exists(trim($lineas[0]))) {
                return trim($lineas[0]);
            }
        }

        // 3. Rutas conocidas en Windows
        if ($esWindows) {
            $patronesWindows = [
                "C:\\Program Files\\PostgreSQL\\18\\bin\\{$nombreEjecutable}",
                "C:\\Program Files\\PostgreSQL\\17\\bin\\{$nombreEjecutable}",
                "C:\\Program Files\\PostgreSQL\\16\\bin\\{$nombreEjecutable}",
                "C:\\Program Files\\PostgreSQL\\15\\bin\\{$nombreEjecutable}",
            ];
            foreach ($patronesWindows as $ruta) {
                if (File::exists($ruta)) {
                    return $ruta;
                }
            }
        } else {
            // Rutas conocidas en Linux / Unix
            $patronesLinux = [
                "/usr/bin/{$nombreEjecutable}",
                "/usr/local/bin/{$nombreEjecutable}",
                "/usr/lib/postgresql/16/bin/{$nombreEjecutable}",
                "/usr/lib/postgresql/15/bin/{$nombreEjecutable}",
            ];
            foreach ($patronesLinux as $ruta) {
                if (File::exists($ruta)) {
                    return $ruta;
                }
            }
        }

        return null;
    }

    /**
     * Empaqueta los archivos relevantes de storage/app excluyendo copias recursivas de backups.
     */
    protected function empaquetarArchivosStorage(string $zipDestino): int
    {
        $zip = new ZipArchive;
        if ($zip->open($zipDestino, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("No se pudo inicializar el archivo ZIP: {$zipDestino}");
        }

        $appStorageDir = storage_path('app');
        $conteoArchivos = 0;

        if (File::isDirectory($appStorageDir)) {
            $directoriosObjetivo = ['public', 'private', 'cotizaciones', 'documentos', 'bajas', 'equipos', 'mantenimientos'];

            foreach ($directoriosObjetivo as $subDir) {
                $rutaCompleta = $appStorageDir.DIRECTORY_SEPARATOR.$subDir;
                if (! File::isDirectory($rutaCompleta)) {
                    continue;
                }

                $archivos = File::allFiles($rutaCompleta);
                foreach ($archivos as $archivo) {
                    $rutaRelativa = substr($archivo->getPathname(), strlen($appStorageDir) + 1);
                    $zip->addFile($archivo->getPathname(), $rutaRelativa);
                    $conteoArchivos++;
                }
            }
        }

        // Añadir archivo README de integridad si no hay archivos aún
        if ($conteoArchivos === 0) {
            $zip->addFromString('backup_notice.txt', 'Respaldo de estructura de storage institucional inicializado.');
            $conteoArchivos = 1;
        }

        $zip->close();

        return $conteoArchivos;
    }

    /**
     * Aplica rotación de respaldos eliminando carpetas más antiguas que $diasRetencion.
     */
    protected function aplicarRotacionRespaldos(string $baseDir, int $diasRetencion): int
    {
        $directorios = File::directories($baseDir);
        $limiteSegundos = $diasRetencion * 86400;
        $ahora = time();
        $eliminados = 0;

        foreach ($directorios as $dir) {
            $nombreDir = basename($dir);
            if (! str_starts_with($nombreDir, 'backup_')) {
                continue;
            }

            $mtime = File::lastModified($dir);
            if (($ahora - $mtime) > $limiteSegundos) {
                File::deleteDirectory($dir);
                $eliminados++;
                Log::channel('single')->info('app:backup - Rotación de respaldo expirado', [
                    'directorio' => $dir,
                    'antiguedad_dias' => round(($ahora - $mtime) / 86400, 1),
                ]);
            }
        }

        return $eliminados;
    }

    /**
     * Genera un volcado SQL para entornos de prueba con base de datos SQLite.
     */
    protected function generarDumpSqlite(string $dumpFilePath): bool
    {
        $tablas = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        $sqlContent = "-- SQLite Backup Dump\n";
        foreach ($tablas as $t) {
            $tableName = $t->name;
            $createRow = DB::selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name = ?", [$tableName]);
            if ($createRow && ! empty($createRow->sql)) {
                $sqlContent .= $createRow->sql.";\n";
            }
            $rows = DB::table($tableName)->get();
            foreach ($rows as $row) {
                $rowArr = (array) $row;
                if (! empty($rowArr)) {
                    $columns = implode(', ', array_map(fn ($c) => '"'.$c.'"', array_keys($rowArr)));
                    $values = implode(', ', array_map(fn ($v) => is_null($v) ? 'NULL' : "'".addslashes((string) $v)."'", array_values($rowArr)));
                    $sqlContent .= "INSERT INTO \"{$tableName}\" ({$columns}) VALUES ({$values});\n";
                }
            }
        }
        File::put($dumpFilePath, gzencode($sqlContent, 9));

        return true;
    }

    /**
     * Formatea tamaño en bytes a representación legible.
     */
    protected function formatearBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }
}
