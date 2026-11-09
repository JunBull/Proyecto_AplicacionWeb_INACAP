<?php

namespace Database\Seeders;

use App\Models\Asignatura;
use App\Models\Carrera;
use App\Models\User;
use Illuminate\Database\Seeder;

class AsignaturasInformaticaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $carrera = Carrera::where('codigo', 'INF-ING')->first() ?? Carrera::first();
        if (! $carrera) {
            return;
        }

        $docente = User::where('email', 'docente@inacap.cl')->first();

        $asignaturas = [
            ['codigo' => 'INF-201', 'nombre' => 'Redes y Telecomunicaciones'],
            ['codigo' => 'INF-301', 'nombre' => 'Arquitectura de Computadores y Hardware'],
            ['codigo' => 'INF-401', 'nombre' => 'Sistemas Operativos y Servidores'],
            ['codigo' => 'INF-501', 'nombre' => 'Bases de Datos Relacionales'],
        ];

        foreach ($asignaturas as $asigData) {
            $asignatura = Asignatura::firstOrCreate(
                ['codigo' => $asigData['codigo']],
                [
                    'carrera_id' => $carrera->id,
                    'nombre' => $asigData['nombre'],
                    'activo' => true,
                ]
            );

            if ($docente) {
                $docente->asignaturas()->syncWithoutDetaching([$asignatura->id]);
            }
        }
    }
}
