<?php

namespace Tests\Feature\HitoDos;

use App\Models\Carrera;
use App\Models\Equipo;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\EquipoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CasoPruebaCP04Test extends TestCase
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
            'codigo' => 'INF-02',
            'nombre' => 'Analista Programador',
            'activa' => true,
        ]);

        $this->ubicacion = Ubicacion::create([
            'carrera_id' => $this->carrera->id,
            'nombre' => 'Laboratorio de Computación 01',
            'tipo' => 'laboratorio',
            'padre_id' => null,
        ]);

        $this->panolero = User::factory()->create([
            'email' => 'panolero.cp04@inacap.cl',
            'rol' => 'encargado_panol',
        ]);

        $this->equipoService = app(EquipoService::class);
    }

    /**
     * CP-04: El sistema rechaza siglas informales o nombres no estandarizados (PC-01, SW-L2, etc.).
     */
    public function test_rechaza_nombres_con_siglas_ambiguas_o_informales(): void
    {
        $nombresInvalidos = [
            'PC-01',
            'SW-L2',
            'PC 02',
            'RTR-1',
            'SRV-01',
            'EQ-99',
            'Router',
            'PC',
        ];

        foreach ($nombresInvalidos as $nombreInvalido) {
            $this->assertFalse(
                $this->equipoService->esNombreEstandarizado($nombreInvalido),
                "El nombre '{$nombreInvalido}' debió ser rechazado por infringir la convención de nombres."
            );

            try {
                $this->equipoService->registrarEquipo([
                    'codigo_barras' => 'EQ-FAIL-' . rand(1000, 9999),
                    'nombre' => $nombreInvalido,
                    'marca' => 'Genérica',
                    'modelo' => 'Genérico',
                    'numero_serie' => 'SN-' . rand(1000, 9999),
                    'criticidad' => 'media',
                    'ubicacion_id' => $this->ubicacion->id,
                ], $this->panolero);

                $this->fail("El servidor debió bloquear el registro con nombre ambiguo: '{$nombreInvalido}'");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('nombre', $e->errors());
            }
        }
    }

    /**
     * CP-04: El sistema acepta denominaciones técnicas estandarizadas descriptivas.
     */
    public function test_acepta_nombres_tecnicos_estandarizados(): void
    {
        $nombresValidos = [
            'Computador de Escritorio All-in-One Intel Core i7 - Lab Redes',
            'Switch Administrable Capa 3 24 Puertos Gigabit',
            'Osciloscopio Digital de Banco 100MHz 2 Canales',
            'Servidor de Rack 2U Dell PowerEdge R740 - Datacenter',
        ];

        foreach ($nombresValidos as $index => $nombreValido) {
            $this->assertTrue(
                $this->equipoService->esNombreEstandarizado($nombreValido),
                "El nombre '{$nombreValido}' debió ser aceptado."
            );

            $equipo = $this->equipoService->registrarEquipo([
                'codigo_barras' => 'EQ-VALID-' . ($index + 1),
                'nombre' => $nombreValido,
                'marca' => 'Dell',
                'modelo' => 'Enterprise',
                'numero_serie' => 'SN-OK-' . ($index + 1),
                'criticidad' => 'alta',
                'ubicacion_id' => $this->ubicacion->id,
            ], $this->panolero);

            $this->assertEquals($nombreValido, $equipo->nombre);
        }
    }

    /**
     * CP-04: Búsqueda e identificación unívoca del activo mediante nombre estandarizado y serie.
     */
    public function test_busqueda_univoca_de_activos_por_nombre_estandarizado_y_serie(): void
    {
        $eq1 = $this->equipoService->registrarEquipo([
            'codigo_barras' => 'EQ-SRCH-001',
            'nombre' => 'Computador All-in-One HP EliteOne 800 G6 - Lab Programación',
            'marca' => 'HP',
            'modelo' => 'EliteOne 800 G6',
            'numero_serie' => 'MXL123456A',
            'criticidad' => 'media',
            'ubicacion_id' => $this->ubicacion->id,
        ], $this->panolero);

        $eq2 = $this->equipoService->registrarEquipo([
            'codigo_barras' => 'EQ-SRCH-002',
            'nombre' => 'Osciloscopio Digital Rigol DS1054Z 50MHz - Lab Hardware',
            'marca' => 'Rigol',
            'modelo' => 'DS1054Z',
            'numero_serie' => 'RIG987654B',
            'criticidad' => 'alta',
            'ubicacion_id' => $this->ubicacion->id,
        ], $this->panolero);

        // Búsqueda por palabra clave descriptiva
        $resultadoOsciloscopio = $this->equipoService->buscar('Osciloscopio');
        $this->assertCount(1, $resultadoOsciloscopio);
        $this->assertEquals($eq2->id, $resultadoOsciloscopio->first()->id);

        // Búsqueda por número de serie
        $resultadoSerie = $this->equipoService->buscar('MXL123456A');
        $this->assertCount(1, $resultadoSerie);
        $this->assertEquals($eq1->id, $resultadoSerie->first()->id);

        // Búsqueda por modelo
        $resultadoModelo = $this->equipoService->buscar('EliteOne');
        $this->assertCount(1, $resultadoModelo);
        $this->assertEquals($eq1->id, $resultadoModelo->first()->id);
    }
}
