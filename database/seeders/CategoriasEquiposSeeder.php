<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Equipo;
use App\Models\Mantenimiento;
use App\Models\Proveedor;
use App\Models\Subcategoria;
use App\Models\Ubicacion;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class CategoriasEquiposSeeder extends Seeder
{
    public function run(): void
    {
        $categorias = [
            [
                'slug' => 'computo-movil',
                'nombre' => 'Cómputo Móvil',
                'descripcion' => 'Notebooks, Tablets y Tabletas Digitalizadoras para desarrollo, virtualización, IA o docencia.',
                'icono' => 'laptop',
                'orden' => 1,
                'criticidad_sugerida' => 'media',
            ],
            [
                'slug' => 'redes-telecomunicaciones',
                'nombre' => 'Redes y Telecomunicaciones',
                'descripcion' => 'Switches, Routers, Puntos de Acceso (AP), Firewalls, Teléfonos IP, Certificadores y Testeadores.',
                'icono' => 'server',
                'orden' => 2,
                'criticidad_sugerida' => 'alta',
            ],
            [
                'slug' => 'instrumentacion-electronica',
                'nombre' => 'Instrumentación Electrónica',
                'descripcion' => 'Osciloscopios, Multímetros, Fuentes de Poder regulables DC y Generadores de Señales.',
                'icono' => 'activity',
                'orden' => 3,
                'criticidad_sugerida' => 'alta',
            ],
            [
                'slug' => 'iot-robotica',
                'nombre' => 'Sistemas Embebidos, IoT y Robótica',
                'descripcion' => 'Placas de Desarrollo, Sensores y Actuadores, y Kits de Robótica educativa.',
                'icono' => 'cpu-chip',
                'orden' => 4,
                'criticidad_sugerida' => 'media',
            ],
            [
                'slug' => 'impresion-captura',
                'nombre' => 'Impresión, Captura y Punto de Venta (Auto-ID)',
                'descripcion' => 'Impresoras de Etiquetas térmicas, Impresoras 3D y Lectores de Código 1D/2D y QR.',
                'icono' => 'printer',
                'orden' => 5,
                'criticidad_sugerida' => 'media',
            ],
            [
                'slug' => 'audiovisual-vr',
                'nombre' => 'Audiovisual y Realidad Extendida',
                'descripcion' => 'Proyectores portátiles, Cámaras y Audio profesional, y Visores VR.',
                'icono' => 'video-camera',
                'orden' => 6,
                'criticidad_sugerida' => 'media',
            ],
        ];

        $categoriasMap = [];
        foreach ($categorias as $catData) {
            $cat = Categoria::updateOrCreate(
                ['slug' => $catData['slug']],
                $catData
            );
            $categoriasMap[$catData['slug']] = $cat->id;
        }

        $subcategorias = [
            // 1. Cómputo Móvil
            ['categoria' => 'computo-movil', 'slug' => 'notebooks', 'nombre' => 'Notebooks', 'codigo_prefijo' => 'EQ-NB', 'orden' => 1],
            ['categoria' => 'computo-movil', 'slug' => 'tablets', 'nombre' => 'Tablets', 'codigo_prefijo' => 'EQ-TAB', 'orden' => 2],
            ['categoria' => 'computo-movil', 'slug' => 'tabletas-digitalizadoras', 'nombre' => 'Tabletas Digitalizadoras', 'codigo_prefijo' => 'EQ-TD', 'orden' => 3],

            // 2. Redes y Telecomunicaciones
            ['categoria' => 'redes-telecomunicaciones', 'slug' => 'switches', 'nombre' => 'Switches', 'codigo_prefijo' => 'EQ-SW', 'orden' => 1],
            ['categoria' => 'redes-telecomunicaciones', 'slug' => 'routers', 'nombre' => 'Routers', 'codigo_prefijo' => 'EQ-RTR', 'orden' => 2],
            ['categoria' => 'redes-telecomunicaciones', 'slug' => 'puntos-de-acceso', 'nombre' => 'Puntos de Acceso (AP)', 'codigo_prefijo' => 'EQ-AP', 'orden' => 3],
            ['categoria' => 'redes-telecomunicaciones', 'slug' => 'firewalls', 'nombre' => 'Firewalls', 'codigo_prefijo' => 'EQ-FW', 'orden' => 4],
            ['categoria' => 'redes-telecomunicaciones', 'slug' => 'telefonos-ip', 'nombre' => 'Teléfonos IP', 'codigo_prefijo' => 'EQ-TIP', 'orden' => 5],
            ['categoria' => 'redes-telecomunicaciones', 'slug' => 'certificadores-testeadores', 'nombre' => 'Certificadores y Testeadores', 'codigo_prefijo' => 'EQ-CERT', 'orden' => 6],

            // 3. Instrumentación Electrónica
            ['categoria' => 'instrumentacion-electronica', 'slug' => 'osciloscopios', 'nombre' => 'Osciloscopios', 'codigo_prefijo' => 'EQ-OSC', 'orden' => 1],
            ['categoria' => 'instrumentacion-electronica', 'slug' => 'multimetros', 'nombre' => 'Multímetros', 'codigo_prefijo' => 'EQ-MUL', 'orden' => 2],
            ['categoria' => 'instrumentacion-electronica', 'slug' => 'fuentes-de-poder', 'nombre' => 'Fuentes de Poder', 'codigo_prefijo' => 'EQ-FP', 'orden' => 3],
            ['categoria' => 'instrumentacion-electronica', 'slug' => 'generadores-de-senales', 'nombre' => 'Generadores de Señales', 'codigo_prefijo' => 'EQ-GEN', 'orden' => 4],

            // 4. Sistemas Embebidos, IoT y Robótica
            ['categoria' => 'iot-robotica', 'slug' => 'placas-de-desarrollo', 'nombre' => 'Placas de Desarrollo', 'codigo_prefijo' => 'EQ-PD', 'orden' => 1],
            ['categoria' => 'iot-robotica', 'slug' => 'sensores-actuadores', 'nombre' => 'Sensores y Actuadores', 'codigo_prefijo' => 'EQ-SEN', 'orden' => 2],
            ['categoria' => 'iot-robotica', 'slug' => 'kits-robotica', 'nombre' => 'Kits de Robótica', 'codigo_prefijo' => 'EQ-ROB', 'orden' => 3],

            // 5. Impresión, Captura y Punto de Venta (Auto-ID)
            ['categoria' => 'impresion-captura', 'slug' => 'impresoras-etiquetas', 'nombre' => 'Impresoras de Etiquetas', 'codigo_prefijo' => 'EQ-IE', 'orden' => 1],
            ['categoria' => 'impresion-captura', 'slug' => 'impresoras-3d', 'nombre' => 'Impresoras 3D', 'codigo_prefijo' => 'EQ-3D', 'orden' => 2],
            ['categoria' => 'impresion-captura', 'slug' => 'lectores-codigo', 'nombre' => 'Lectores de Código', 'codigo_prefijo' => 'EQ-LC', 'orden' => 3],

            // 6. Audiovisual y Realidad Extendida
            ['categoria' => 'audiovisual-vr', 'slug' => 'proyectores', 'nombre' => 'Proyectores', 'codigo_prefijo' => 'EQ-PROY', 'orden' => 1],
            ['categoria' => 'audiovisual-vr', 'slug' => 'camaras-audio', 'nombre' => 'Cámaras y Audio', 'codigo_prefijo' => 'EQ-CAM', 'orden' => 2],
            ['categoria' => 'audiovisual-vr', 'slug' => 'visores-vr', 'nombre' => 'Visores VR', 'codigo_prefijo' => 'EQ-VR', 'orden' => 3],
        ];

        $subcategoriasMap = [];
        foreach ($subcategorias as $subData) {
            $sub = Subcategoria::updateOrCreate(
                ['slug' => $subData['slug']],
                [
                    'categoria_id' => $categoriasMap[$subData['categoria']],
                    'nombre' => $subData['nombre'],
                    'codigo_prefijo' => $subData['codigo_prefijo'],
                    'orden' => $subData['orden'],
                    'activo' => true,
                ]
            );
            $subcategoriasMap[$subData['slug']] = $sub->id;
        }

        // Retirar antigua categoría 'herramientas-taller' y sus 3 equipos demo heredados si no tienen historial (Opción A)
        $equiposLegacyRetirar = ['EQ-CRIMP-01', 'EQ-HAK-FX8-01', 'EQ-IFX-PRO-01'];
        foreach ($equiposLegacyRetirar as $codigoRetirar) {
            $eqRetirar = Equipo::where('codigo_barras', $codigoRetirar)->first();
            if (
                $eqRetirar
                && ! $eqRetirar->mantenimientos()->exists()
                && ! $eqRetirar->fallas()->exists()
                && ! $eqRetirar->bajasReemplazos()->exists()
                && ! $eqRetirar->detallesSolicitud()->exists()
            ) {
                $eqRetirar->documentos()->delete();
                $eqRetirar->delete();
            }
        }

        $antiguaCategoria = Categoria::where('slug', 'herramientas-taller')->first();
        if ($antiguaCategoria) {
            Equipo::where('categoria_id', $antiguaCategoria->id)
                ->update([
                    'categoria_id' => $categoriasMap['redes-telecomunicaciones'],
                    'subcategoria_id' => $subcategoriasMap['certificadores-testeadores'],
                ]);
            $antiguaCategoria->delete();
        }

        // Mapa de migración in-place de códigos antiguos al formato unificado PREFIJO-MARCA3-MODELO-NN
        $equivalenciasCodigo = [
            'EQ-LNV-T14-01' => 'EQ-NB-LNV-T14-01',
            'EQ-LNV-T14-02' => 'EQ-NB-LNV-T14-02',
            'EQ-APL-MBP-01' => 'EQ-NB-APL-MBP-01',
            'EQ-DEL-LAT-01' => 'EQ-NB-DEL-LAT-01',
            'EQ-LNV-TAB-01' => 'EQ-TAB-LNV-M11-01',
            'EQ-SAM-TAB-01' => 'EQ-TAB-SAM-S9FE-01',
            'EQ-WAC-INT-01' => 'EQ-TD-WAC-INT-01',
            'EQ-CSC-2960-01' => 'EQ-SW-CSC-2960-01',
            'EQ-ARU-2930-01' => 'EQ-SW-ARU-2930-01',
            'EQ-CSC-ISR-01' => 'EQ-RTR-CSC-ISR-01',
            'EQ-MIK-CCR-01' => 'EQ-RTR-MIK-CCR-01',
            'EQ-UBI-U6P-01' => 'EQ-AP-UBI-U6P-01',
            'EQ-FTN-60F-01' => 'EQ-FW-FTN-60F-01',
            'EQ-CSC-7821-01' => 'EQ-TIP-CSC-7821-01',
            'EQ-FLK-DSX-01' => 'EQ-CERT-FLK-DSX-01',
            'EQ-TEK-1052-01' => 'EQ-OSC-TEK-1052-01',
            'EQ-RIG-1054-01' => 'EQ-OSC-RIG-1054-01',
            'EQ-FLK-117-04' => 'EQ-MUL-FLK-117-01',
            'EQ-UNI-T89-01' => 'EQ-MUL-UNI-UT89-01',
            'EQ-KOR-3005-01' => 'EQ-FP-KOR-3005-01',
            'EQ-SIG-1032-01' => 'EQ-GEN-SIG-1032-01',
            'EQ-ARD-SEN-02' => 'EQ-SEN-ARD-KIT-01',
            'EQ-ARD-UNO-01' => 'EQ-PD-ARD-UNO-01',
            'EQ-ESP-W32-01' => 'EQ-PD-ESP-W32-01',
            'EQ-RPI-4B4-01' => 'EQ-PD-RPI-PI4-01',
            'EQ-NV-JET-01' => 'EQ-PD-NVD-JET-01',
            'EQ-ROB-ARM-01' => 'EQ-ROB-HIW-ARM-01',
            'EQ-ZEB-220-01' => 'EQ-IE-ZEB-ZD22-01',
            'EQ-BRO-QL8-01' => 'EQ-IE-BRO-QL80-01',
            'EQ-CRE-E3V-01' => 'EQ-3D-CRE-E3V3-01',
            'EQ-BMB-P1S-01' => 'EQ-3D-BMB-P1S-01',
            'EQ-ZEB-DS2-01' => 'EQ-LC-ZEB-DS22-01',
            'EQ-EPS-DAT-01' => 'EQ-PROY-EPS-E20-01',
            'EQ-LOG-C92-01' => 'EQ-CAM-LOG-C920-01',
            'EQ-MET-QST-01' => 'EQ-VR-MET-QST3-01',
        ];

        foreach ($equivalenciasCodigo as $codigoViejo => $codigoNuevo) {
            $existenteViejo = Equipo::where('codigo_barras', $codigoViejo)->first();
            if ($existenteViejo && ! Equipo::where('codigo_barras', $codigoNuevo)->exists()) {
                $existenteViejo->update(['codigo_barras' => $codigoNuevo]);
            }
        }

        // Ubicaciones de referencia
        $ubicacionPanol = Ubicacion::where('nombre', 'LIKE', '%Pañol%')->value('id') ?? Ubicacion::first()?->id ?? 1;
        $ubicacionLtel = Ubicacion::where('nombre', 'LTEL')->value('id') ?? Ubicacion::where('nombre', 'LIKE', '%Redes%')->value('id') ?? $ubicacionPanol;
        $ubicacionLinf = Ubicacion::where('nombre', 'LINF')->value('id') ?? Ubicacion::where('nombre', 'LIKE', '%Computación%')->value('id') ?? $ubicacionPanol;

        // Catálogo completo de equipos unificado (Categoría -> Subcategoría -> Marca/Modelo)
        $equipos = [
            // 1. Cómputo Móvil — Notebooks
            [
                'codigo_barras' => 'EQ-NB-LNV-T14-01',
                'nombre' => 'Notebook Lenovo ThinkPad T14',
                'marca' => 'Lenovo',
                'modelo' => 'ThinkPad T14 Gen 3',
                'numero_serie' => 'SN-LNV-T14-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['computo-movil'],
                'subcategoria_id' => $subcategoriasMap['notebooks'],
            ],
            [
                'codigo_barras' => 'EQ-NB-LNV-T14-02',
                'nombre' => 'Notebook Lenovo ThinkPad T14',
                'marca' => 'Lenovo',
                'modelo' => 'ThinkPad T14 Gen 3',
                'numero_serie' => 'SN-LNV-T14-002',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['computo-movil'],
                'subcategoria_id' => $subcategoriasMap['notebooks'],
            ],
            [
                'codigo_barras' => 'EQ-NB-APL-MBP-01',
                'nombre' => 'MacBook Pro 14 Pulgadas Apple M3',
                'marca' => 'Apple',
                'modelo' => 'MacBook Pro 14',
                'numero_serie' => 'SN-APL-MBP-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLinf,
                'categoria_id' => $categoriasMap['computo-movil'],
                'subcategoria_id' => $subcategoriasMap['notebooks'],
            ],
            [
                'codigo_barras' => 'EQ-NB-DEL-LAT-01',
                'nombre' => 'Notebook Dell Latitude 5440 Core i7',
                'marca' => 'Dell',
                'modelo' => 'Latitude 5440',
                'numero_serie' => 'SN-DEL-LAT-001',
                'criticidad' => 'media',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLinf,
                'categoria_id' => $categoriasMap['computo-movil'],
                'subcategoria_id' => $subcategoriasMap['notebooks'],
            ],

            // 1. Cómputo Móvil — Tablets
            [
                'codigo_barras' => 'EQ-TAB-LNV-M11-01',
                'nombre' => 'Tablet Lenovo Tab M11 128GB Wi-Fi',
                'marca' => 'Lenovo',
                'modelo' => 'Tab M11',
                'numero_serie' => 'SN-LNV-TAB-001',
                'criticidad' => 'media',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLinf,
                'categoria_id' => $categoriasMap['computo-movil'],
                'subcategoria_id' => $subcategoriasMap['tablets'],
            ],
            [
                'codigo_barras' => 'EQ-TAB-SAM-S9FE-01',
                'nombre' => 'Tablet Samsung Galaxy Tab S9 FE con S-Pen',
                'marca' => 'Samsung',
                'modelo' => 'Galaxy Tab S9 FE',
                'numero_serie' => 'SN-SAM-TAB-001',
                'criticidad' => 'media',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionPanol,
                'categoria_id' => $categoriasMap['computo-movil'],
                'subcategoria_id' => $subcategoriasMap['tablets'],
            ],
            [
                'codigo_barras' => 'EQ-TAB-APL-IPAD-01',
                'nombre' => 'Tablet Apple iPad 10ma Generación Wi-Fi',
                'marca' => 'Apple',
                'modelo' => 'iPad 10th Gen',
                'numero_serie' => 'SN-APL-IPAD-001',
                'criticidad' => 'media',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLinf,
                'categoria_id' => $categoriasMap['computo-movil'],
                'subcategoria_id' => $subcategoriasMap['tablets'],
            ],

            // 1. Cómputo Móvil — Tabletas Digitalizadoras
            [
                'codigo_barras' => 'EQ-TD-WAC-INT-01',
                'nombre' => 'Tableta Digitalizadora Wacom Intuos Pro Medium',
                'marca' => 'Wacom',
                'modelo' => 'Intuos Pro PTH-660',
                'numero_serie' => 'SN-WAC-INT-001',
                'criticidad' => 'media',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionPanol,
                'categoria_id' => $categoriasMap['computo-movil'],
                'subcategoria_id' => $subcategoriasMap['tabletas-digitalizadoras'],
            ],
            [
                'codigo_barras' => 'EQ-TD-HUI-H610-01',
                'nombre' => 'Tableta Digitalizadora Huion Inspiroy H610 Pro',
                'marca' => 'Huion',
                'modelo' => 'Inspiroy H610 Pro V2',
                'numero_serie' => 'SN-HUI-H610-001',
                'criticidad' => 'baja',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionPanol,
                'categoria_id' => $categoriasMap['computo-movil'],
                'subcategoria_id' => $subcategoriasMap['tabletas-digitalizadoras'],
            ],

            // 2. Redes y Telecomunicaciones — Switches
            [
                'codigo_barras' => 'EQ-SW-CSC-2960-01',
                'nombre' => 'Switch Cisco Catalyst Capa 3 24 Puertos',
                'marca' => 'Cisco Systems',
                'modelo' => 'Catalyst 2960X-24TD-L',
                'numero_serie' => 'SN-CSC-2960-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['redes-telecomunicaciones'],
                'subcategoria_id' => $subcategoriasMap['switches'],
            ],
            [
                'codigo_barras' => 'EQ-SW-ARU-2930-01',
                'nombre' => 'Switch Aruba 2930F 24 Puertos PoE+',
                'marca' => 'Aruba',
                'modelo' => '2930F JL253A',
                'numero_serie' => 'SN-ARU-2930-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['redes-telecomunicaciones'],
                'subcategoria_id' => $subcategoriasMap['switches'],
            ],

            // 2. Redes y Telecomunicaciones — Routers
            [
                'codigo_barras' => 'EQ-RTR-CSC-ISR-01',
                'nombre' => 'Router Cisco ISR 4321 Gigabit Ethernet',
                'marca' => 'Cisco Systems',
                'modelo' => 'ISR 4321/K9',
                'numero_serie' => 'SN-CSC-ISR-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['redes-telecomunicaciones'],
                'subcategoria_id' => $subcategoriasMap['routers'],
            ],
            [
                'codigo_barras' => 'EQ-RTR-MIK-CCR-01',
                'nombre' => 'Router MikroTik Cloud Core Router 16G',
                'marca' => 'MikroTik',
                'modelo' => 'CCR2004-16G-2S+',
                'numero_serie' => 'SN-MIK-CCR-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['redes-telecomunicaciones'],
                'subcategoria_id' => $subcategoriasMap['routers'],
            ],
            [
                'codigo_barras' => 'EQ-RTR-TPL-AX55-01',
                'nombre' => 'Router Inalámbrico de Prueba Wi-Fi 6 Doble Banda',
                'marca' => 'TP-Link',
                'modelo' => 'Archer AX55',
                'numero_serie' => 'SN-TPL-AX55-001',
                'criticidad' => 'baja',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['redes-telecomunicaciones'],
                'subcategoria_id' => $subcategoriasMap['routers'],
            ],

            // 2. Redes y Telecomunicaciones — Puntos de Acceso (AP)
            [
                'codigo_barras' => 'EQ-AP-UBI-U6P-01',
                'nombre' => 'Punto de Acceso Wi-Fi 6 Ubiquiti UniFi Pro',
                'marca' => 'Ubiquiti',
                'modelo' => 'UniFi 6 Pro',
                'numero_serie' => 'SN-UBI-U6P-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['redes-telecomunicaciones'],
                'subcategoria_id' => $subcategoriasMap['puntos-de-acceso'],
            ],
            [
                'codigo_barras' => 'EQ-AP-CSC-MR33-01',
                'nombre' => 'Antena Wi-Fi Empresarial Cisco Meraki MR33',
                'marca' => 'Cisco Systems',
                'modelo' => 'Meraki MR33',
                'numero_serie' => 'SN-CSC-MR33-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['redes-telecomunicaciones'],
                'subcategoria_id' => $subcategoriasMap['puntos-de-acceso'],
            ],

            // 2. Redes y Telecomunicaciones — Firewalls
            [
                'codigo_barras' => 'EQ-FW-FTN-60F-01',
                'nombre' => 'Appliance Firewall Fortinet FortiGate 60F',
                'marca' => 'Fortinet',
                'modelo' => 'FortiGate 60F',
                'numero_serie' => 'SN-FTN-60F-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['redes-telecomunicaciones'],
                'subcategoria_id' => $subcategoriasMap['firewalls'],
            ],
            [
                'codigo_barras' => 'EQ-FW-CSC-ASA-01',
                'nombre' => 'Appliance Firewall Cisco ASA 5506-X',
                'marca' => 'Cisco Systems',
                'modelo' => 'ASA 5506-X',
                'numero_serie' => 'SN-CSC-ASA-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['redes-telecomunicaciones'],
                'subcategoria_id' => $subcategoriasMap['firewalls'],
            ],

            // 2. Redes y Telecomunicaciones — Teléfonos IP
            [
                'codigo_barras' => 'EQ-TIP-CSC-7821-01',
                'nombre' => 'Teléfono IP Cisco 7821 VoIP Dos Líneas',
                'marca' => 'Cisco Systems',
                'modelo' => 'CP-7821-K9',
                'numero_serie' => 'SN-CSC-7821-001',
                'criticidad' => 'media',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['redes-telecomunicaciones'],
                'subcategoria_id' => $subcategoriasMap['telefonos-ip'],
            ],
            [
                'codigo_barras' => 'EQ-TIP-GRD-GXP-01',
                'nombre' => 'Terminal VoIP Grandstream GXP1625 HD',
                'marca' => 'Grandstream',
                'modelo' => 'GXP1625',
                'numero_serie' => 'SN-GRD-GXP-001',
                'criticidad' => 'baja',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['redes-telecomunicaciones'],
                'subcategoria_id' => $subcategoriasMap['telefonos-ip'],
            ],

            // 2. Redes y Telecomunicaciones — Certificadores y Testeadores
            [
                'codigo_barras' => 'EQ-CERT-FLK-DSX-01',
                'nombre' => 'Certificador de Cableado Fluke Networks DSX-5000',
                'marca' => 'Fluke Networks',
                'modelo' => 'DSX-5000 CableAnalyzer',
                'numero_serie' => 'SN-FLK-DSX-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['redes-telecomunicaciones'],
                'subcategoria_id' => $subcategoriasMap['certificadores-testeadores'],
            ],
            [
                'codigo_barras' => 'EQ-CERT-SUM-FUS-01',
                'nombre' => 'Fusionadora de Fibra Óptica de Alineación por Núcleo',
                'marca' => 'Sumitomo',
                'modelo' => 'Type-72C',
                'numero_serie' => 'SN-SUM-FUS-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['redes-telecomunicaciones'],
                'subcategoria_id' => $subcategoriasMap['certificadores-testeadores'],
            ],
            [
                'codigo_barras' => 'EQ-CERT-FLK-MS2-01',
                'nombre' => 'Comprobador de Cableado de Red Fluke MicroScanner2',
                'marca' => 'Fluke Networks',
                'modelo' => 'MicroScanner2',
                'numero_serie' => 'SN-FLK-MS2-001',
                'criticidad' => 'media',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['redes-telecomunicaciones'],
                'subcategoria_id' => $subcategoriasMap['certificadores-testeadores'],
            ],

            // 3. Instrumentación Electrónica — Osciloscopios
            [
                'codigo_barras' => 'EQ-OSC-TEK-1052-01',
                'nombre' => 'Osciloscopio Digital 2 Canales 50MHz',
                'marca' => 'Tektronix',
                'modelo' => 'TBS1052B-EDU',
                'numero_serie' => 'SN-TEK-1052-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['instrumentacion-electronica'],
                'subcategoria_id' => $subcategoriasMap['osciloscopios'],
            ],
            [
                'codigo_barras' => 'EQ-OSC-RIG-1054-01',
                'nombre' => 'Osciloscopio Digital 4 Canales 50MHz Rigol',
                'marca' => 'Rigol',
                'modelo' => 'DS1054Z',
                'numero_serie' => 'SN-RIG-1054-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['instrumentacion-electronica'],
                'subcategoria_id' => $subcategoriasMap['osciloscopios'],
            ],

            // 3. Instrumentación Electrónica — Multímetros
            [
                'codigo_barras' => 'EQ-MUL-FLK-117-01',
                'nombre' => 'Multímetro Digital True RMS Fluke 117',
                'marca' => 'Fluke',
                'modelo' => '117 Electricians',
                'numero_serie' => 'SN-FLK-117-001',
                'criticidad' => 'media',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['instrumentacion-electronica'],
                'subcategoria_id' => $subcategoriasMap['multimetros'],
            ],
            [
                'codigo_barras' => 'EQ-MUL-UNI-UT89-01',
                'nombre' => 'Multímetro Digital Tester Uni-T True RMS',
                'marca' => 'Uni-T',
                'modelo' => 'UT89X',
                'numero_serie' => 'SN-UNI-T89-001',
                'criticidad' => 'baja',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['instrumentacion-electronica'],
                'subcategoria_id' => $subcategoriasMap['multimetros'],
            ],

            // 3. Instrumentación Electrónica — Fuentes de Poder
            [
                'codigo_barras' => 'EQ-FP-KOR-3005-01',
                'nombre' => 'Fuente de Poder Regulable DC 30V 5A de Laboratorio',
                'marca' => 'Korad',
                'modelo' => 'KA3005D',
                'numero_serie' => 'SN-KOR-3005-001',
                'criticidad' => 'media',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['instrumentacion-electronica'],
                'subcategoria_id' => $subcategoriasMap['fuentes-de-poder'],
            ],

            // 3. Instrumentación Electrónica — Generadores de Señales
            [
                'codigo_barras' => 'EQ-GEN-SIG-1032-01',
                'nombre' => 'Generador de Funciones y Formas de Onda 30MHz',
                'marca' => 'Siglent',
                'modelo' => 'SDG1032X',
                'numero_serie' => 'SN-SIG-1032-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['instrumentacion-electronica'],
                'subcategoria_id' => $subcategoriasMap['generadores-de-senales'],
            ],
            [
                'codigo_barras' => 'EQ-GEN-RIG-DSA-01',
                'nombre' => 'Analizador de Espectro RF 9kHz a 1.5GHz',
                'marca' => 'Rigol',
                'modelo' => 'DSA815-TG',
                'numero_serie' => 'SN-RIG-DSA-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['instrumentacion-electronica'],
                'subcategoria_id' => $subcategoriasMap['generadores-de-senales'],
            ],

            // 4. Sistemas Embebidos, IoT y Robótica — Placas de Desarrollo
            [
                'codigo_barras' => 'EQ-PD-ARD-UNO-01',
                'nombre' => 'Placa de Desarrollo Arduino Uno R3 ATmega328P',
                'marca' => 'Arduino',
                'modelo' => 'Uno R3 SMD',
                'numero_serie' => 'SN-ARD-UNO-001',
                'criticidad' => 'baja',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionPanol,
                'categoria_id' => $categoriasMap['iot-robotica'],
                'subcategoria_id' => $subcategoriasMap['placas-de-desarrollo'],
            ],
            [
                'codigo_barras' => 'EQ-PD-ARD-MEGA-01',
                'nombre' => 'Placa de Desarrollo Arduino Mega 2560 Rev3',
                'marca' => 'Arduino',
                'modelo' => 'Mega 2560 Rev3',
                'numero_serie' => 'SN-ARD-MEGA-001',
                'criticidad' => 'baja',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionPanol,
                'categoria_id' => $categoriasMap['iot-robotica'],
                'subcategoria_id' => $subcategoriasMap['placas-de-desarrollo'],
            ],
            [
                'codigo_barras' => 'EQ-PD-ESP-W32-01',
                'nombre' => 'Módulo IoT Wi-Fi y Bluetooth ESP32 NodeMCU',
                'marca' => 'Espressif',
                'modelo' => 'ESP32-WROOM-32D',
                'numero_serie' => 'SN-ESP-W32-001',
                'criticidad' => 'baja',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionPanol,
                'categoria_id' => $categoriasMap['iot-robotica'],
                'subcategoria_id' => $subcategoriasMap['placas-de-desarrollo'],
            ],
            [
                'codigo_barras' => 'EQ-PD-RPI-PI4-01',
                'nombre' => 'Microcomputador Raspberry Pi 4 Model B 4GB RAM',
                'marca' => 'Raspberry Pi',
                'modelo' => 'Raspberry Pi 4 4GB',
                'numero_serie' => 'SN-RPI-4B4-001',
                'criticidad' => 'media',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLinf,
                'categoria_id' => $categoriasMap['iot-robotica'],
                'subcategoria_id' => $subcategoriasMap['placas-de-desarrollo'],
            ],
            [
                'codigo_barras' => 'EQ-PD-RPI-PI5-01',
                'nombre' => 'Microcomputador Raspberry Pi 5 8GB RAM',
                'marca' => 'Raspberry Pi',
                'modelo' => 'Raspberry Pi 5 8GB',
                'numero_serie' => 'SN-RPI-PI5-001',
                'criticidad' => 'media',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLinf,
                'categoria_id' => $categoriasMap['iot-robotica'],
                'subcategoria_id' => $subcategoriasMap['placas-de-desarrollo'],
            ],
            [
                'codigo_barras' => 'EQ-PD-NVD-JET-01',
                'nombre' => 'Placa de Inteligencia Artificial NVIDIA Jetson Nano',
                'marca' => 'NVIDIA',
                'modelo' => 'Jetson Nano 4GB B01',
                'numero_serie' => 'SN-NV-JET-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLinf,
                'categoria_id' => $categoriasMap['iot-robotica'],
                'subcategoria_id' => $subcategoriasMap['placas-de-desarrollo'],
            ],

            // 4. Sistemas Embebidos, IoT y Robótica — Sensores y Actuadores
            [
                'codigo_barras' => 'EQ-SEN-ARD-KIT-01',
                'nombre' => 'Kit Sensores Ultrasónicos y RFID para Docencia',
                'marca' => 'Arduino',
                'modelo' => 'Kit Sensores 37 en 1',
                'numero_serie' => 'SN-ARD-002',
                'criticidad' => 'baja',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLinf,
                'categoria_id' => $categoriasMap['iot-robotica'],
                'subcategoria_id' => $subcategoriasMap['sensores-actuadores'],
            ],
            [
                'codigo_barras' => 'EQ-SEN-GEN-DHT-01',
                'nombre' => 'Módulo Sensores Temperatura Humedad DHT22 y Giroscopio',
                'marca' => 'Genérico',
                'modelo' => 'DHT22 + MPU6050',
                'numero_serie' => 'SN-SEN-DHT-001',
                'criticidad' => 'baja',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionPanol,
                'categoria_id' => $categoriasMap['iot-robotica'],
                'subcategoria_id' => $subcategoriasMap['sensores-actuadores'],
            ],
            [
                'codigo_barras' => 'EQ-SEN-TOW-SG90-01',
                'nombre' => 'Kit Actuadores Servomotores de Precisión SG90',
                'marca' => 'TowerPro',
                'modelo' => 'SG90 Pack 10',
                'numero_serie' => 'SN-TOW-SG90-001',
                'criticidad' => 'baja',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionPanol,
                'categoria_id' => $categoriasMap['iot-robotica'],
                'subcategoria_id' => $subcategoriasMap['sensores-actuadores'],
            ],
            [
                'codigo_barras' => 'EQ-SEN-INT-D435-01',
                'nombre' => 'Cámara de Profundidad Intel RealSense D435i',
                'marca' => 'Intel',
                'modelo' => 'RealSense D435i',
                'numero_serie' => 'SN-INT-D435-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLinf,
                'categoria_id' => $categoriasMap['iot-robotica'],
                'subcategoria_id' => $subcategoriasMap['sensores-actuadores'],
            ],

            // 4. Sistemas Embebidos, IoT y Robótica — Kits de Robótica
            [
                'codigo_barras' => 'EQ-ROB-HIW-ARM-01',
                'nombre' => 'Brazo Robótico Educativo Programable 6 Grados',
                'marca' => 'Hiwonder',
                'modelo' => 'xArm 1S',
                'numero_serie' => 'SN-ROB-ARM-001',
                'criticidad' => 'media',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLinf,
                'categoria_id' => $categoriasMap['iot-robotica'],
                'subcategoria_id' => $subcategoriasMap['kits-robotica'],
            ],
            [
                'codigo_barras' => 'EQ-ROB-MOD-KIT-01',
                'nombre' => 'Kit Armable de Robótica Educativa Programable',
                'marca' => 'Makeblock',
                'modelo' => 'mBot2 Rover',
                'numero_serie' => 'SN-ROB-MBOT-001',
                'criticidad' => 'media',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLinf,
                'categoria_id' => $categoriasMap['iot-robotica'],
                'subcategoria_id' => $subcategoriasMap['kits-robotica'],
            ],
            [
                'codigo_barras' => 'EQ-ROB-DJI-TLL-01',
                'nombre' => 'Dron Programable Educativo Python y Scratch',
                'marca' => 'DJI',
                'modelo' => 'Tello EDU',
                'numero_serie' => 'SN-DJI-TLL-001',
                'criticidad' => 'media',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLinf,
                'categoria_id' => $categoriasMap['iot-robotica'],
                'subcategoria_id' => $subcategoriasMap['kits-robotica'],
            ],

            // 5. Impresión, Captura y Punto de Venta (Auto-ID) — Impresoras de Etiquetas
            [
                'codigo_barras' => 'EQ-IE-ZEB-ZD22-01',
                'nombre' => 'Impresora Térmica de Etiquetas Zebra ZD220',
                'marca' => 'Zebra Technologies',
                'modelo' => 'ZD220 Direct Thermal',
                'numero_serie' => 'SN-ZEB-220-001',
                'criticidad' => 'media',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionPanol,
                'categoria_id' => $categoriasMap['impresion-captura'],
                'subcategoria_id' => $subcategoriasMap['impresoras-etiquetas'],
            ],
            [
                'codigo_barras' => 'EQ-IE-BRO-QL80-01',
                'nombre' => 'Impresora de Etiquetas Profesional Brother QL-800',
                'marca' => 'Brother',
                'modelo' => 'QL-800',
                'numero_serie' => 'SN-BRO-QL8-001',
                'criticidad' => 'media',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionPanol,
                'categoria_id' => $categoriasMap['impresion-captura'],
                'subcategoria_id' => $subcategoriasMap['impresoras-etiquetas'],
            ],

            // 5. Impresión, Captura y Punto de Venta (Auto-ID) — Impresoras 3D
            [
                'codigo_barras' => 'EQ-3D-CRE-E3V3-01',
                'nombre' => 'Impresora 3D de Prototipado Creality Ender 3 V3',
                'marca' => 'Creality',
                'modelo' => 'Ender 3 V3 SE',
                'numero_serie' => 'SN-CRE-E3V-001',
                'criticidad' => 'media',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLinf,
                'categoria_id' => $categoriasMap['impresion-captura'],
                'subcategoria_id' => $subcategoriasMap['impresoras-3d'],
            ],
            [
                'codigo_barras' => 'EQ-3D-BMB-P1S-01',
                'nombre' => 'Impresora 3D de Alta Velocidad Bambu Lab P1S',
                'marca' => 'Bambu Lab',
                'modelo' => 'P1S Combo',
                'numero_serie' => 'SN-BMB-P1S-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLinf,
                'categoria_id' => $categoriasMap['impresion-captura'],
                'subcategoria_id' => $subcategoriasMap['impresoras-3d'],
            ],

            // 5. Impresión, Captura y Punto de Venta (Auto-ID) — Lectores de Código
            [
                'codigo_barras' => 'EQ-LC-ZEB-DS22-01',
                'nombre' => 'Lector de Códigos de Barra 1D y 2D Zebra DS2208',
                'marca' => 'Zebra Technologies',
                'modelo' => 'DS2208 Handheld Scanner',
                'numero_serie' => 'SN-ZEB-DS2-001',
                'criticidad' => 'baja',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionPanol,
                'categoria_id' => $categoriasMap['impresion-captura'],
                'subcategoria_id' => $subcategoriasMap['lectores-codigo'],
            ],
            [
                'codigo_barras' => 'EQ-LC-HON-1950-01',
                'nombre' => 'Pistola Escáner Honeywell Xenon XP 1950g 1D/2D y QR',
                'marca' => 'Honeywell',
                'modelo' => 'Xenon XP 1950g',
                'numero_serie' => 'SN-HON-1950-001',
                'criticidad' => 'baja',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionPanol,
                'categoria_id' => $categoriasMap['impresion-captura'],
                'subcategoria_id' => $subcategoriasMap['lectores-codigo'],
            ],

            // 6. Audiovisual y Realidad Extendida — Proyectores
            [
                'codigo_barras' => 'EQ-PROY-EPS-E20-01',
                'nombre' => 'Proyector Data Show Portátil 3LCD 3600 Lúmenes',
                'marca' => 'Epson',
                'modelo' => 'PowerLite E20',
                'numero_serie' => 'SN-EPS-DAT-001',
                'criticidad' => 'media',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionPanol,
                'categoria_id' => $categoriasMap['audiovisual-vr'],
                'subcategoria_id' => $subcategoriasMap['proyectores'],
            ],
            [
                'codigo_barras' => 'EQ-PROY-VWS-PA50-01',
                'nombre' => 'Proyector Data Show Portátil ViewSonic 3800 Lúmenes',
                'marca' => 'ViewSonic',
                'modelo' => 'PA503S',
                'numero_serie' => 'SN-VWS-PA50-001',
                'criticidad' => 'media',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionPanol,
                'categoria_id' => $categoriasMap['audiovisual-vr'],
                'subcategoria_id' => $subcategoriasMap['proyectores'],
            ],

            // 6. Audiovisual y Realidad Extendida — Cámaras y Audio
            [
                'codigo_barras' => 'EQ-CAM-LOG-C920-01',
                'nombre' => 'Cámara Web Full HD 1080p con Micrófono Estéreo',
                'marca' => 'Logitech',
                'modelo' => 'C920 HD Pro',
                'numero_serie' => 'SN-LOG-C92-001',
                'criticidad' => 'baja',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionPanol,
                'categoria_id' => $categoriasMap['audiovisual-vr'],
                'subcategoria_id' => $subcategoriasMap['camaras-audio'],
            ],
            [
                'codigo_barras' => 'EQ-CAM-ROD-NTG4-01',
                'nombre' => 'Micrófono Direccional de Estudio para Grabación Docente',
                'marca' => 'Rode',
                'modelo' => 'NTG4+',
                'numero_serie' => 'SN-ROD-NTG4-001',
                'criticidad' => 'baja',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionPanol,
                'categoria_id' => $categoriasMap['audiovisual-vr'],
                'subcategoria_id' => $subcategoriasMap['camaras-audio'],
            ],
            [
                'codigo_barras' => 'EQ-CAM-EDF-1280-01',
                'nombre' => 'Parlantes Activos de Sala Multimedia Estéreo',
                'marca' => 'Edifier',
                'modelo' => 'R1280T',
                'numero_serie' => 'SN-EDF-1280-001',
                'criticidad' => 'baja',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionPanol,
                'categoria_id' => $categoriasMap['audiovisual-vr'],
                'subcategoria_id' => $subcategoriasMap['camaras-audio'],
            ],

            // 6. Audiovisual y Realidad Extendida — Visores VR
            [
                'codigo_barras' => 'EQ-VR-MET-QST3-01',
                'nombre' => 'Visor de Realidad Virtual y Mixta Meta Quest 3 128GB',
                'marca' => 'Meta',
                'modelo' => 'Quest 3',
                'numero_serie' => 'SN-MET-QST-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLinf,
                'categoria_id' => $categoriasMap['audiovisual-vr'],
                'subcategoria_id' => $subcategoriasMap['visores-vr'],
            ],
            [
                'codigo_barras' => 'EQ-VR-HTC-VIVE-01',
                'nombre' => 'Visor de Realidad Virtual HTC Vive Pro 2 Kit Completo',
                'marca' => 'HTC',
                'modelo' => 'Vive Pro 2',
                'numero_serie' => 'SN-HTC-VIVE-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLinf,
                'categoria_id' => $categoriasMap['audiovisual-vr'],
                'subcategoria_id' => $subcategoriasMap['visores-vr'],
            ],
        ];

        foreach ($equipos as $eqData) {
            Equipo::updateOrCreate(
                ['codigo_barras' => $eqData['codigo_barras']],
                $eqData
            );
        }

        // Historial de Mantenimiento para EQ-NB-LNV-T14-01 (coincidente con Mockup 3)
        $lenovoT14 = Equipo::where('codigo_barras', 'EQ-NB-LNV-T14-01')->first();
        if ($lenovoT14) {
            $proveedor = Proveedor::first();

            Mantenimiento::updateOrCreate(
                [
                    'equipo_id' => $lenovoT14->id,
                    'fecha_programada' => Carbon::parse('2026-01-18'),
                ],
                [
                    'proveedor_id' => $proveedor?->id,
                    'tipo' => 'preventivo',
                    'subtipo' => 'REV-A',
                    'frecuencia' => 'semestral',
                    'fecha_realizada' => Carbon::parse('2026-01-18'),
                    'estado' => 'realizado',
                    'observaciones' => 'Actualización BIOS y diagnóstico general de memoria RAM.',
                ]
            );

            Mantenimiento::updateOrCreate(
                [
                    'equipo_id' => $lenovoT14->id,
                    'fecha_programada' => Carbon::parse('2026-05-22'),
                ],
                [
                    'proveedor_id' => $proveedor?->id,
                    'tipo' => 'correctivo',
                    'subtipo' => 'MC-PLAN',
                    'frecuencia' => 'no_aplica',
                    'fecha_realizada' => Carbon::parse('2026-05-22'),
                    'estado' => 'realizado',
                    'observaciones' => 'Reemplazo de teclado por daño físico en barra espaciadora.',
                ]
            );

            Mantenimiento::updateOrCreate(
                [
                    'equipo_id' => $lenovoT14->id,
                    'fecha_programada' => Carbon::parse('2026-10-15'),
                ],
                [
                    'proveedor_id' => $proveedor?->id,
                    'tipo' => 'preventivo',
                    'subtipo' => 'REV-A',
                    'frecuencia' => 'semestral',
                    'fecha_realizada' => Carbon::parse('2026-10-15'),
                    'estado' => 'realizado',
                    'observaciones' => 'Limpieza interna de ventilador, cambio de pasta térmica y ajuste de bisagras.',
                ]
            );

            Mantenimiento::updateOrCreate(
                [
                    'equipo_id' => $lenovoT14->id,
                    'fecha_programada' => Carbon::parse('2027-04-15'),
                ],
                [
                    'proveedor_id' => $proveedor?->id,
                    'tipo' => 'preventivo',
                    'subtipo' => 'REV-A',
                    'frecuencia' => 'semestral',
                    'fecha_realizada' => null,
                    'estado' => 'programado',
                    'observaciones' => 'Mantenimiento preventivo periódico semestral programado según matriz técnica.',
                ]
            );
        }
    }
}
