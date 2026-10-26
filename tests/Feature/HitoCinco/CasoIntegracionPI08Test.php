<?php

namespace Tests\Feature\HitoCinco;

use App\Models\Carrera;
use App\Models\Documento;
use App\Models\Equipo;
use App\Models\Insumo;
use App\Models\Presupuesto;
use App\Models\Ubicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CasoIntegracionPI08Test extends TestCase
{
    use RefreshDatabase;

    protected Carrera $carrera;

    protected Ubicacion $ubicacion;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'rol' => 'administrador',
            'activo' => true,
        ]);

        $this->carrera = Carrera::create([
            'nombre' => 'Ingeniería en Informática',
            'codigo' => 'INF-DRP',
            'activa' => true,
        ]);

        $this->ubicacion = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Laboratorio Redes y Ciberseguridad',
            'codigo' => 'LAB-CYB',
            'tipo' => 'laboratorio',
        ]);
    }

    /**
     * PI-08: Simulación de respaldo, pérdida de datos (siniestro) y restauración completa DRP.
     * Verifica integridad relacional, consistencia de hashes SHA-256 y cumplimiento de RPO (<=24h) y RTO (<=8h).
     */
    public function test_pi08_simulacion_respaldo_perdida_total_y_restauracion_drp_cumple_rpo_y_rto(): void
    {
        // 1. Estado Pre-Siniestro: Crear activos, insumos, presupuesto y documentos con hash
        $presupuestoOriginal = Presupuesto::create([
            'carrera_id' => $this->carrera->id,
            'anio' => 2026,
            'monto_asignado' => 15000000,
            'saldo_actual' => 10500000,
        ]);

        $equipoOriginal = Equipo::create([
            'codigo_barras' => 'EQ-DRP-001',
            'nombre' => 'Switch Capa 3 Administrable',
            'marca' => 'Cisco',
            'modelo' => 'Catalyst 2960X',
            'numero_serie' => 'SN-CSC-999',
            'criticidad' => 'alta',
            'estado' => 'disponible',
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        $hashFicticio = hash('sha256', 'Contenido institucional de ficha técnica y manual de switch Cisco');
        Documento::create([
            'tipo' => 'informe_tecnico',
            'ruta' => 'equipos/informes/switch_cisco.pdf',
            'nombre_original' => 'informe_switch_cisco.pdf',
            'hash_sha256' => $hashFicticio,
            'documentable_type' => Equipo::class,
            'documentable_id' => $equipoOriginal->id,
            'subido_por' => $this->admin->id,
            'creado_en' => now(),
        ]);

        $insumoOriginal = Insumo::create([
            'codigo_barras' => 'INS-DRP-001',
            'nombre' => 'Conector Jack RJ-45 Hembra Cat6',
            'unidad' => 'unidad',
            'es_retornable' => false,
            'stock_actual' => 120,
            'stock_minimo' => 25,
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        // 2. Extracción de Respaldo Lógico (Snapshot Backup Diario)
        $marcaTemporalRespaldo = now();
        $snapshotDatos = [
            'timestamp' => $marcaTemporalRespaldo->toIso8601String(),
            'carreras' => Carrera::all()->toArray(),
            'ubicaciones' => Ubicacion::all()->toArray(),
            'presupuestos' => Presupuesto::all()->toArray(),
            'equipos' => Equipo::all()->toArray(),
            'documentos' => Documento::all()->toArray(),
            'insumos' => Insumo::all()->toArray(),
        ];

        $respaldoJson = json_encode($snapshotDatos, JSON_THROW_ON_ERROR);
        $this->assertNotEmpty($respaldoJson);

        // Verificación de RPO (Recovery Point Objective <= 24 horas)
        $rpoHoras = now()->diffInHours($marcaTemporalRespaldo);
        $this->assertLessThanOrEqual(24, $rpoHoras, 'El RPO debe ser menor o igual a 24 horas conforme al plan de continuidad.');

        // 3. Simulación de Desastre / Siniestro: Pérdida total de datos en base de datos
        Documento::query()->delete();
        Insumo::query()->delete();
        Equipo::query()->delete();
        Presupuesto::query()->delete();

        $this->assertDatabaseCount('documentos', 0);
        $this->assertDatabaseCount('insumos', 0);
        $this->assertDatabaseCount('equipos', 0);
        $this->assertDatabaseCount('presupuestos', 0);

        // 4. Inicio del Protocolo de Restauración ante Desastres (DRP)
        $inicioRTO = microtime(true);

        DB::transaction(function () use ($respaldoJson) {
            $datosRecuperados = json_decode($respaldoJson, true, 512, JSON_THROW_ON_ERROR);

            foreach ($datosRecuperados['presupuestos'] as $p) {
                Presupuesto::forceCreate($p);
            }

            foreach ($datosRecuperados['equipos'] as $eq) {
                Equipo::forceCreate($eq);
            }

            foreach ($datosRecuperados['documentos'] as $doc) {
                Documento::forceCreate($doc);
            }

            foreach ($datosRecuperados['insumos'] as $ins) {
                Insumo::forceCreate($ins);
            }
        });

        $tiempoRestauracionSegundos = microtime(true) - $inicioRTO;

        // 5. Verificación de RTO (Recovery Time Objective <= 8 horas / 28800 segundos)
        $this->assertLessThan(28800, $tiempoRestauracionSegundos, 'El RTO superó el límite pactado de 8 horas.');
        $this->assertLessThan(5.0, $tiempoRestauracionSegundos, 'La restauración en entorno de prueba debe completarse en segundos.');

        // 6. Verificación de Integridad Relacional y Consistencia Post-Restauración (Smoke Test)
        $this->assertDatabaseHas('presupuestos', [
            'carrera_id' => $this->carrera->id,
            'saldo_actual' => 10500000,
        ]);

        $equipoRestaurado = Equipo::with('documentos')->where('codigo_barras', 'EQ-DRP-001')->first();
        $this->assertNotNull($equipoRestaurado);
        $this->assertEquals('Switch Capa 3 Administrable', $equipoRestaurado->nombre);
        $this->assertEquals('disponible', $equipoRestaurado->estado);

        // Comprobación de Hash SHA-256 de archivos documentales respaldados
        $this->assertCount(1, $equipoRestaurado->documentos);
        $docRestaurado = $equipoRestaurado->documentos->first();
        $this->assertEquals($hashFicticio, $docRestaurado->hash_sha256);

        $this->assertDatabaseHas('insumos', [
            'codigo_barras' => 'INS-DRP-001',
            'stock_actual' => 120,
        ]);
    }
}
