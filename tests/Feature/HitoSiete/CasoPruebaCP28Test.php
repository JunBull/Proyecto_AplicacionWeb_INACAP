<?php

namespace Tests\Feature\HitoSiete;

use App\Jobs\EnviarReporteIncidenciaJob;
use App\Mail\ReporteIncidenciaMail;
use App\Models\Carrera;
use App\Models\Equipo;
use App\Models\Falla;
use App\Models\Insumo;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\FallaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CasoPruebaCP28Test extends TestCase
{
    use RefreshDatabase;

    protected User $docente;

    protected User $panolero;

    protected User $admin;

    protected Carrera $carrera;

    protected Ubicacion $laboratorio;

    protected FallaService $fallaService;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->fallaService = app(FallaService::class);

        $this->carrera = Carrera::create([
            'codigo' => 'ELC-01',
            'nombre' => 'Electricidad Industrial',
            'activa' => true,
        ]);

        $this->laboratorio = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Laboratorio de Motores Eléctricos',
            'tipo' => 'laboratorio',
        ]);

        $this->docente = User::factory()->create([
            'name' => 'Docente Roberto Lagos',
            'email' => 'roberto.lagos@inacap.cl',
            'rol' => 'docente',
            'activo' => true,
        ]);

        $this->panolero = User::factory()->create([
            'name' => 'Pañolero Rodrigo Fuentealba',
            'email' => 'rodrigo.panol@inacap.cl',
            'rol' => 'encargado_panol',
            'activo' => true,
        ]);

        $this->admin = User::factory()->create([
            'name' => 'Admin Directora María',
            'email' => 'maria.admin@inacap.cl',
            'rol' => 'administrador',
            'activo' => true,
        ]);
    }

    /**
     * CP-28: Reportar incidencia de equipo en mal estado genera Falla, cambia estado a 'en_mantenimiento',
     * almacena el PDF con hash SHA-256 en 'documentos' y encola el trabajo de correo.
     */
    public function test_reportar_incidencia_equipo_genera_falla_pdf_y_encola_job(): void
    {
        Queue::fake();

        $equipo = Equipo::create([
            'codigo_barras' => 'EQ-ELC-INC-001',
            'nombre' => 'Variador de Frecuencia Schneider ATV320',
            'marca' => 'Schneider Electric',
            'modelo' => 'ATV320U15N4B',
            'numero_serie' => 'SN-SCH-001',
            'estado' => 'disponible',
            'ubicacion_id' => $this->laboratorio->id,
        ]);

        $falla = $this->fallaService->reportarIncidencia($this->docente, [
            'tipo_item' => 'equipo',
            'equipo_id' => $equipo->id,
            'ubicacion_id' => $this->laboratorio->id,
            'descripcion' => 'El variador presenta cortocircuito en bornera de salida y olor a quemado.',
            'severidad' => 'critica',
        ]);

        // 1. Verificación de la falla y transición de estado del equipo a 'en_mantenimiento'
        $this->assertDatabaseHas('fallas', [
            'id' => $falla->id,
            'equipo_id' => $equipo->id,
            'reportada_por' => $this->docente->id,
            'estado' => 'reportada',
        ]);
        $this->assertEquals('en_mantenimiento', $equipo->fresh()->estado);

        // 2. Verificación de almacenamiento físico del archivo PDF
        $rutaPdf = "incidencias/Reporte_Incidencia_{$falla->id}.pdf";
        Storage::disk('public')->assertExists($rutaPdf);
        $pdfContenido = Storage::disk('public')->get($rutaPdf);
        $this->assertStringStartsWith('%PDF-1.4', $pdfContenido);

        // 3. Verificación de registro polimórfico en la tabla 'documentos' con hash SHA-256
        $hashEsperado = hash('sha256', $pdfContenido);
        $this->assertDatabaseHas('documentos', [
            'documentable_type' => Falla::class,
            'documentable_id' => $falla->id,
            'tipo' => 'informe_tecnico',
            'ruta' => $rutaPdf,
            'hash_sha256' => $hashEsperado,
            'subido_por' => $this->docente->id,
        ]);

        // 4. Verificación de encolado del Job asíncrono
        Queue::assertPushed(EnviarReporteIncidenciaJob::class, function ($job) use ($falla) {
            return $job->falla->id === $falla->id;
        });
    }

    /**
     * CP-28: Reportar incidencia desde la interfaz Livewire del Docente (modal en solicitudes).
     */
    public function test_interfaz_docente_reporta_incidencia_insumo_satisfactoriamente(): void
    {
        Queue::fake();

        $this->actingAs($this->docente);

        $insumo = Insumo::create([
            'codigo_barras' => 'INS-ELC-INC-002',
            'nombre' => 'Cables de Prueba Banana-Caimán 1000V',
            'unidad' => 'par',
            'es_retornable' => true,
            'stock_actual' => 12.0,
            'stock_minimo' => 4.0,
            'ubicacion_id' => $this->laboratorio->id,
        ]);

        Volt::test('pages.solicitudes.index')
            ->call('abrirModalIncidencia')
            ->set('tipoIncidencia', 'insumo')
            ->set('incidenciaInsumoId', $insumo->id)
            ->set('incidenciaUbicacionId', $this->laboratorio->id)
            ->set('incidenciaDescripcion', 'Aislación rota en los terminales con riesgo de contacto eléctrico.')
            ->set('incidenciaSeveridad', 'alta')
            ->call('registrarIncidencia')
            ->assertHasNoErrors()
            ->assertSee('¡Incidencia');

        $this->assertDatabaseHas('fallas', [
            'insumo_id' => $insumo->id,
            'reportada_por' => $this->docente->id,
            'descripcion' => 'Aislación rota en los terminales con riesgo de contacto eléctrico.',
        ]);
    }

    public function test_interfaz_docente_reporta_incidencia_en_sala_satisfactoriamente(): void
    {
        Queue::fake();

        $this->actingAs($this->docente);

        $equipo = Equipo::create([
            'codigo_barras' => 'EQ-ELC-INC-009',
            'nombre' => 'Multímetro Fluke 87V',
            'marca' => 'Fluke',
            'modelo' => '87V',
            'numero_serie' => 'SN-FLUKE-009',
            'estado' => 'disponible',
            'ubicacion_id' => $this->laboratorio->id,
        ]);

        Volt::test('pages.solicitudes.index')
            ->call('abrirModalIncidencia')
            ->set('tipoIncidencia', 'equipo')
            ->set('incidenciaEquipoId', $equipo->id)
            ->call('seleccionarTipoDestinoIncidencia', 'sala')
            ->set('incidenciaEdificioDestino', 'Edificio C')
            ->set('incidenciaSalaDestino', 'C201')
            ->set('incidenciaDescripcion', 'Fusible interno quemado durante medición en sala de clases.')
            ->set('incidenciaSeveridad', 'media')
            ->call('registrarIncidencia')
            ->assertHasNoErrors()
            ->assertSee('¡Incidencia');

        $this->assertDatabaseHas('fallas', [
            'equipo_id' => $equipo->id,
            'reportada_por' => $this->docente->id,
            'tipo_destino' => 'sala',
            'edificio_destino' => 'Edificio C',
            'espacio_destino' => 'Edificio C - C201',
            'descripcion' => 'Fusible interno quemado durante medición en sala de clases.',
        ]);
    }

    /**
     * CP-28: Ejecución del Job EnviarReporteIncidenciaJob envía el correo con PDF a Pañolero y Administrador.
     */
    public function test_job_envio_reporte_incidencia_despacha_correos_con_pdf(): void
    {
        Mail::fake();

        $insumo = Insumo::create([
            'codigo_barras' => 'INS-ELC-INC-003',
            'nombre' => 'Pinza Amperimétrica True-RMS',
            'unidad' => 'unidad',
            'es_retornable' => true,
            'stock_actual' => 4.0,
            'stock_minimo' => 2.0,
            'ubicacion_id' => $this->laboratorio->id,
        ]);

        $falla = $this->fallaService->reportarIncidencia($this->docente, [
            'tipo_item' => 'insumo',
            'insumo_id' => $insumo->id,
            'ubicacion_id' => $this->laboratorio->id,
            'descripcion' => 'Pantalla LCD dañada, no despliega dígitos de medición.',
        ]);

        // Ejecutar el Job directamente
        $job = new EnviarReporteIncidenciaJob($falla);
        $job->handle();

        // Verificar que se enviaron correos al pañolero y al administrador
        Mail::assertSent(ReporteIncidenciaMail::class, function ($mail) {
            return $mail->hasTo($this->panolero->email);
        });

        Mail::assertSent(ReporteIncidenciaMail::class, function ($mail) {
            return $mail->hasTo($this->admin->email);
        });
    }
}
