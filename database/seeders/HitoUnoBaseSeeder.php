<?php

namespace Database\Seeders;

use App\Models\Carrera;
use App\Models\Ubicacion;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class HitoUnoBaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Carreras iniciales de Informática
        $carreraIng = Carrera::firstOrCreate(
            ['codigo' => 'INF-ING'],
            [
                'nombre' => 'Ingeniería en Informática',
                'activa' => true,
            ]
        );

        $carreraAp = Carrera::firstOrCreate(
            ['codigo' => 'INF-AP'],
            [
                'nombre' => 'Analista Programador',
                'activa' => true,
            ]
        );

        // 2. Jerarquía base de ubicaciones (pañol, laboratorios y subdivisiones granulares)
        $panolCentral = Ubicacion::firstOrCreate(
            [
                'carrera_id' => $carreraIng->id,
                'nombre' => 'Pañol Central de Informática',
            ],
            [
                'padre_id' => null,
                'tipo' => 'panol',
            ]
        );

        $estanteA = Ubicacion::firstOrCreate(
            [
                'carrera_id' => $carreraIng->id,
                'nombre' => 'Estante Metálico A',
                'padre_id' => $panolCentral->id,
            ],
            [
                'tipo' => 'mueble',
            ]
        );

        Ubicacion::firstOrCreate(
            [
                'carrera_id' => $carreraIng->id,
                'nombre' => 'Cajón A-01: Microcontroladores y Sensores',
                'padre_id' => $estanteA->id,
            ],
            [
                'tipo' => 'cajon',
            ]
        );

        Ubicacion::firstOrCreate(
            [
                'carrera_id' => $carreraIng->id,
                'nombre' => 'Cajón A-02: Cables y Conectores de Red',
                'padre_id' => $estanteA->id,
            ],
            [
                'tipo' => 'cajon',
            ]
        );

        Ubicacion::firstOrCreate(
            [
                'carrera_id' => $carreraIng->id,
                'nombre' => 'Laboratorio L1 - Redes y Telecomunicaciones',
            ],
            [
                'padre_id' => null,
                'tipo' => 'laboratorio',
            ]
        );

        Ubicacion::firstOrCreate(
            [
                'carrera_id' => $carreraAp->id,
                'nombre' => 'Laboratorio L2 - Desarrollo de Software',
            ],
            [
                'padre_id' => null,
                'tipo' => 'laboratorio',
            ]
        );

        // 3. Tres usuarios de prueba para los roles institucionales
        $password = Hash::make('Password123!');

        $admin = User::firstOrCreate(
            ['email' => 'admin@inacap.cl'],
            [
                'nombre' => 'Administrador Sistema',
                'password' => $password,
                'rol' => 'administrador',
                'activo' => true,
            ]
        );
        $admin->carreras()->syncWithoutDetaching([$carreraIng->id, $carreraAp->id]);

        $panolero = User::firstOrCreate(
            ['email' => 'panolero@inacap.cl'],
            [
                'nombre' => 'Encargado Pañol',
                'password' => $password,
                'rol' => 'encargado_panol',
                'activo' => true,
            ]
        );
        $panolero->carreras()->syncWithoutDetaching([$carreraIng->id, $carreraAp->id]);

        $docente = User::firstOrCreate(
            ['email' => 'docente@inacap.cl'],
            [
                'nombre' => 'Docente Informática',
                'password' => $password,
                'rol' => 'docente',
                'activo' => true,
            ]
        );
        $docente->carreras()->syncWithoutDetaching([$carreraIng->id]);
    }
}
