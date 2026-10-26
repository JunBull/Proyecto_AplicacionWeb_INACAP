<?php

namespace Database\Seeders;

use App\Models\Carrera;
use App\Models\Equipo;
use App\Models\Insumo;
use App\Models\Presupuesto;
use App\Models\Proveedor;
use App\Models\Ubicacion;
use Illuminate\Database\Seeder;

class DatosOperativosDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $carreraIng = Carrera::where('codigo', 'INF-ING')->first();
        $carreraAp = Carrera::where('codigo', 'INF-AP')->first();

        if (! $carreraIng || ! $carreraAp) {
            return;
        }

        // 1. Presupuestos 2026
        Presupuesto::firstOrCreate(
            [
                'carrera_id' => $carreraIng->id,
                'anio' => 2026,
            ],
            [
                'monto_asignado' => 15000000.00,
                'saldo_actual' => 15000000.00,
            ]
        );

        Presupuesto::firstOrCreate(
            [
                'carrera_id' => $carreraAp->id,
                'anio' => 2026,
            ],
            [
                'monto_asignado' => 12000000.00,
                'saldo_actual' => 12000000.00,
            ]
        );

        // 2. Proveedores iniciales
        Proveedor::firstOrCreate(
            ['rut' => '76.111.222-3'],
            [
                'razon_social' => 'Insumos y Redes Austral SpA',
                'correo' => 'ventas@redesaustral.cl',
                'telefono' => '+56 45 221 4455',
                'tipo' => 'insumos',
                'activo' => true,
            ]
        );

        Proveedor::firstOrCreate(
            ['rut' => '77.333.444-5'],
            [
                'razon_social' => 'Soporte y Tecnología Computacional Ltda.',
                'correo' => 'contacto@soportecomp.cl',
                'telefono' => '+56 45 233 8899',
                'tipo' => 'mantenimiento',
                'activo' => true,
            ]
        );

        Proveedor::firstOrCreate(
            ['rut' => '78.555.666-7'],
            [
                'razon_social' => 'Comercializadora Hardware Temuco SpA',
                'correo' => 'ventas@hardtemuco.cl',
                'telefono' => '+56 45 277 1122',
                'tipo' => 'ambos',
                'activo' => true,
            ]
        );

        // 3. Ubicaciones
        $cajonRed = Ubicacion::where('nombre', 'like', '%Cables y Conectores%')->first();
        $cajonSensores = Ubicacion::where('nombre', 'like', '%Microcontroladores%')->first();
        $estante = Ubicacion::where('nombre', 'like', '%Estante Metálico A%')->first();
        $labRedes = Ubicacion::where('nombre', 'like', '%Laboratorio L1%')->first();

        $ubicacionDefecto = $cajonRed?->id ?? Ubicacion::first()->id;

        // 4. Insumos iniciales
        Insumo::firstOrCreate(
            ['codigo_barras' => 'INS-RJ45-CAT6'],
            [
                'nombre' => 'Conector RJ-45 Cat6 Macho',
                'unidad' => 'unidad',
                'es_retornable' => false,
                'stock_actual' => 250,
                'stock_minimo' => 50,
                'ubicacion_id' => $cajonRed?->id ?? $ubicacionDefecto,
            ]
        );

        Insumo::firstOrCreate(
            ['codigo_barras' => 'INS-BOBINA-UTP'],
            [
                'nombre' => 'Bobina Cable UTP Cat6 305m',
                'unidad' => 'bobina',
                'es_retornable' => false,
                'stock_actual' => 5,
                'stock_minimo' => 2,
                'ubicacion_id' => $estante?->id ?? $ubicacionDefecto,
            ]
        );

        Insumo::firstOrCreate(
            ['codigo_barras' => 'INS-TESTER-RED'],
            [
                'nombre' => 'Tester Comprobador de Red RJ-45',
                'unidad' => 'unidad',
                'es_retornable' => true,
                'stock_actual' => 12,
                'stock_minimo' => 3,
                'ubicacion_id' => $cajonSensores?->id ?? $ubicacionDefecto,
            ]
        );

        Insumo::firstOrCreate(
            ['codigo_barras' => 'INS-MULTIMETRO-DIG'],
            [
                'nombre' => 'Multímetro Digital Autorango',
                'unidad' => 'unidad',
                'es_retornable' => true,
                'stock_actual' => 8,
                'stock_minimo' => 2,
                'ubicacion_id' => $cajonSensores?->id ?? $ubicacionDefecto,
            ]
        );

        // 5. Equipos iniciales
        $ubicacionLab = $labRedes?->id ?? $ubicacionDefecto;

        Equipo::firstOrCreate(
            ['codigo_barras' => 'EQ-LNV-T14-01'],
            [
                'nombre' => 'Notebook Lenovo ThinkPad T14',
                'marca' => 'Lenovo',
                'modelo' => 'ThinkPad T14 Gen 3',
                'numero_serie' => 'SN-LNV-T14-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLab,
            ]
        );

        Equipo::firstOrCreate(
            ['codigo_barras' => 'EQ-LNV-T14-02'],
            [
                'nombre' => 'Notebook Lenovo ThinkPad T14',
                'marca' => 'Lenovo',
                'modelo' => 'ThinkPad T14 Gen 3',
                'numero_serie' => 'SN-LNV-T14-002',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLab,
            ]
        );

        Equipo::firstOrCreate(
            ['codigo_barras' => 'EQ-CSC-2960-01'],
            [
                'nombre' => 'Switch Cisco Catalyst Capa 3',
                'marca' => 'Cisco',
                'modelo' => 'Catalyst 2960X-24TD-L',
                'numero_serie' => 'SN-CSC-2960-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLab,
            ]
        );

        Equipo::firstOrCreate(
            ['codigo_barras' => 'EQ-TEK-1052-01'],
            [
                'nombre' => 'Osciloscopio Digital 2 Canales',
                'marca' => 'Tektronix',
                'modelo' => 'TBS1052B-EDU',
                'numero_serie' => 'SN-TEK-1052-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLab,
            ]
        );
    }
}
