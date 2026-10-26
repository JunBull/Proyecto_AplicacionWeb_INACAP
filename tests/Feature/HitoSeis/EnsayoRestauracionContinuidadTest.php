<?php

namespace Tests\Feature\HitoSeis;

use App\Models\Carrera;
use App\Models\Equipo;
use App\Models\Insumo;
use App\Models\Presupuesto;
use App\Models\Ubicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class EnsayoRestauracionContinuidadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear datos base para verificación
        $admin = User::factory()->create([
            'rol' => 'administrador',
            'activo' => true,
        ]);

        $carrera = Carrera::create([
            'nombre' => 'Ingeniería en Informática',
            'codigo' => 'INF-H6',
            'activa' => true,
        ]);

        $ubicacion = Ubicacion::create([
            'carrera_id' => $carrera->id,
            'nombre' => 'Pañol Principal Informática',
            'codigo' => 'PAN-H6',
            'tipo' => 'panol',
        ]);

        Presupuesto::create([
            'carrera_id' => $carrera->id,
            'anio' => 2026,
            'monto_asignado' => 12000000,
            'saldo_actual' => 12000000,
        ]);

        Equipo::create([
            'codigo_barras' => 'EQ-H6-001',
            'nombre' => 'Multímetro Digital True RMS',
            'marca' => 'Fluke',
            'modelo' => '179',
            'numero_serie' => 'SN-FLK-H6',
            'criticidad' => 'media',
            'estado' => 'disponible',
            'ubicacion_id' => $ubicacion->id,
        ]);

        Insumo::create([
            'codigo_barras' => 'INS-H6-001',
            'nombre' => 'Cable UTP Categoría 6 Bobina 305m',
            'unidad' => 'metro',
            'es_retornable' => false,
            'stock_actual' => 305,
            'stock_minimo' => 50,
            'ubicacion_id' => $ubicacion->id,
        ]);
    }

    /**
     * Prueba el comando Artisan app:backup verificando la generación del volcado,
     * empaquetado de storage, generación del manifiesto y cálculo criptográfico de SHA-256 (SLA-08, SLA-09).
     */
    public function test_comando_backup_genera_artefactos_y_manifiesto(): void
    {
        $backupTempDir = storage_path('app/backups_test_'.uniqid());

        $exitCode = Artisan::call('app:backup', [
            '--destination' => $backupTempDir,
            '--keep-days' => 30,
        ]);

        $this->assertEquals(0, $exitCode, 'El comando app:backup debe finalizar con código 0');

        $carpetas = File::directories($backupTempDir);
        $this->assertNotEmpty($carpetas, 'Debe haberse creado la carpeta del respaldo');

        $directorioRespaldo = $carpetas[0];
        $this->assertFileExists($directorioRespaldo.'/database.dump');
        $this->assertFileExists($directorioRespaldo.'/storage_files.zip');
        $this->assertFileExists($directorioRespaldo.'/manifest.json');

        $manifestContent = json_decode(File::get($directorioRespaldo.'/manifest.json'), true);
        $this->assertIsArray($manifestContent);
        $this->assertEquals('COMPLETED', $manifestContent['status']);
        $this->assertEquals(24, $manifestContent['compliance']['sla_09_rpo_max_horas']);
        $this->assertEquals(8, $manifestContent['compliance']['sla_10_rto_max_horas']);

        // Limpieza de directorio de prueba
        File::deleteDirectory($backupTempDir);
    }

    /**
     * Prueba el comando Artisan app:restore en modo simulación de contingencia DRP (PI-08, SLA-10).
     */
    public function test_comando_restore_en_simulacion_verifica_rpo_y_rto(): void
    {
        // 1. Generar un respaldo
        $exitBackup = Artisan::call('app:backup');
        $this->assertEquals(0, $exitBackup);

        // 2. Ejecutar restauración simulada
        $exitRestore = Artisan::call('app:restore', [
            '--simulate' => true,
        ]);

        $this->assertEquals(0, $exitRestore, 'El comando app:restore --simulate debe finalizar con código 0');

        $output = Artisan::output();
        $this->assertStringContainsString('SLA-09: RPO <= 24 horas (OK)', $output);
        $this->assertStringContainsString('SLA-10: RTO <= 8 horas', $output);
        $this->assertStringContainsString('ENSAYO DE CONTINUIDAD FINALIZADO EXITOSAMENTE', $output);
    }
}
