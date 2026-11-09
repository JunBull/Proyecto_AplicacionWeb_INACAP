<?php

namespace Database\Seeders;

use App\Models\Carrera;
use App\Models\Ubicacion;
use Illuminate\Database\Seeder;

class PoblarSalasYLaboratoriosRealesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $carrera = Carrera::first();
        if (! $carrera) {
            return;
        }

        $laboratoriosReales = [
            'LTEL', 'LINF', 'LEICA', 'LEICA 2', 'LNET',
            'C2', 'C8', 'C6',
            'ALCR', 'ALCS', 'ALPLC', 'ALEP', 'ALCP', 'ALEE', 'ALCE',
            'MAC1', 'MAC2', 'TD1', 'TD2',
        ];

        foreach ($laboratoriosReales as $labNombre) {
            Ubicacion::firstOrCreate(
                [
                    'carrera_id' => $carrera->id,
                    'nombre' => $labNombre,
                ],
                [
                    'padre_id' => null,
                    'tipo' => 'laboratorio',
                ]
            );
        }
    }
}
