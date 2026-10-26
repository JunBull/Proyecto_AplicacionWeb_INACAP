<?php

namespace Tests\Feature\HitoDos;

use App\Models\Carrera;
use App\Models\Documento;
use App\Models\Equipo;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\EquipoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CasoPruebaCP03Test extends TestCase
{
    use RefreshDatabase;

    protected Carrera $carrera;
    protected Ubicacion $ubicacion;
    protected User $panolero;
    protected EquipoService $equipoService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->carrera = Carrera::create([
            'codigo' => 'INF-01',
            'nombre' => 'Ingeniería en Informática',
            'activa' => true,
        ]);

        $this->ubicacion = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Laboratorio de Redes y Telecomunicaciones',
            'tipo' => 'laboratorio',
            'padre_id' => null,
        ]);

        $this->panolero = User::factory()->create([
            'email' => 'panolero.cp03@inacap.cl',
            'rol' => 'encargado_panol',
        ]);

        $this->equipoService = app(EquipoService::class);
    }

    /**
     * CP-03: Registrar un equipo con ficha técnica completa conserva exitosamente sus atributos y fotografía.
     */
    public function test_registro_ficha_tecnica_completa_con_exito_y_documento_fotografia(): void
    {
        Storage::fake('public');

        $foto = UploadedFile::fake()->create('switch_cisco.jpg', 100, 'image/jpeg');

        $datos = [
            'codigo_barras' => 'EQ-CISCO-2960-001',
            'nombre' => 'Switch Administrable Capa 3 24 Puertos Gigabit',
            'marca' => 'Cisco Systems',
            'modelo' => 'Catalyst 2960-X',
            'numero_serie' => 'FOC2134S0AB',
            'criticidad' => 'alta',
            'ubicacion_id' => $this->ubicacion->id,
            'fotografia' => $foto,
            'fecha_adquisicion' => '2026-03-15',
        ];

        $equipo = $this->equipoService->registrarEquipo($datos, $this->panolero);

        $this->assertInstanceOf(Equipo::class, $equipo);
        $this->assertDatabaseHas('equipos', [
            'id' => $equipo->id,
            'codigo_barras' => 'EQ-CISCO-2960-001',
            'marca' => 'Cisco Systems',
            'modelo' => 'Catalyst 2960-X',
            'numero_serie' => 'FOC2134S0AB',
            'criticidad' => 'alta',
            'estado' => 'disponible',
            'ubicacion_id' => $this->ubicacion->id,
        ]);

        // Verifica que la fotografía haya sido asociada a la tabla polimórfica 'documentos'
        $this->assertDatabaseHas('documentos', [
            'documentable_type' => Equipo::class,
            'documentable_id' => $equipo->id,
            'tipo' => 'otro',
            'subido_por' => $this->panolero->id,
        ]);

        $this->assertTrue($equipo->documentos->isNotEmpty());
        $this->assertEquals('Switch Administrable Capa 3 24 Puertos Gigabit', $equipo->nombre);
    }

    /**
     * CP-03: Ficha técnica incompleta es rechazada por el servidor informando los campos pendientes.
     */
    public function test_registro_ficha_tecnica_incompleta_es_rechazada_con_errores_claros(): void
    {
        $datosIncompletos = [
            'codigo_barras' => '',
            'nombre' => '',
            'marca' => '',
            'modelo' => '',
            'numero_serie' => '',
            'ubicacion_id' => 999999, // Ubicación inexistente
        ];

        try {
            $this->equipoService->registrarEquipo($datosIncompletos, $this->panolero);
            $this->fail('Se esperaba una ValidationException debido a datos obligatorios ausentes.');
        } catch (ValidationException $e) {
            $errores = $e->errors();

            $this->assertArrayHasKey('codigo_barras', $errores);
            $this->assertArrayHasKey('nombre', $errores);
            $this->assertArrayHasKey('marca', $errores);
            $this->assertArrayHasKey('modelo', $errores);
            $this->assertArrayHasKey('numero_serie', $errores);
            $this->assertArrayHasKey('ubicacion_id', $errores);
        }

        $this->assertEquals(0, Equipo::count());
        $this->assertEquals(0, Documento::count());
    }
}
