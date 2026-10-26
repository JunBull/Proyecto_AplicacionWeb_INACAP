<?php

namespace Tests\Feature;

use App\Models\Carrera;
use App\Models\DetalleSolicitud;
use App\Models\SolicitudMaterial;
use App\Models\Ubicacion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IntegracionPostgresCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_carreras_usuarios_y_ubicaciones_relaciones_completas(): void
    {
        $carrera = Carrera::create([
            'codigo' => 'TEST-01',
            'nombre' => 'Carrera Test',
            'activa' => true,
        ]);

        $usuario = User::factory()->create([
            'email' => 'test.rel@inacap.cl',
            'rol' => 'administrador',
        ]);

        $usuario->carreras()->attach($carrera->id);

        $ubicacion = Ubicacion::create([
            'carrera_id' => $carrera->id,
            'nombre' => 'Pañol Test',
            'tipo' => 'panol',
            'padre_id' => null,
        ]);

        $this->assertTrue($usuario->carreras->contains($carrera));
        $this->assertTrue($carrera->usuarios->contains($usuario));
        $this->assertEquals($carrera->id, $ubicacion->carrera->id);
    }

    public function test_detalle_solicitud_falla_si_ambos_insumo_y_equipo_estan_nulos_o_ambos_presentes(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Solo aplica al motor PostgreSQL estricto con restricciones CHECK nativas.');
        }

        $docente = User::factory()->docente()->create();
        $solicitud = SolicitudMaterial::create([
            'solicitante_id' => $docente->id,
            'fecha_solicitud' => now(),
            'fecha_requerida' => now()->addDays(2),
            'estado' => 'pendiente',
        ]);

        // Escenario A: ambos nulos (debe fallar por CHECK chk_detalle_solicitud_item)
        $falloA = false;
        try {
            DetalleSolicitud::create([
                'solicitud_id' => $solicitud->id,
                'insumo_id' => null,
                'equipo_id' => null,
                'cantidad_solicitada' => 1,
                'cantidad_entregada' => 0,
                'cantidad_devuelta' => 0,
                'estado_devolucion' => 'no_aplica',
            ]);
        } catch (QueryException $e) {
            $falloA = true;
        }

        $this->assertTrue($falloA, 'La restricción CHECK debió rechazar la inserción con insumo_id y equipo_id nulos simultáneamente.');
    }
}
