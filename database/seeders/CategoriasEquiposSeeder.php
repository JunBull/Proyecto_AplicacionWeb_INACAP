<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Equipo;
use App\Models\Mantenimiento;
use App\Models\Proveedor;
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
                'descripcion' => 'Notebooks, Tablets y Tabletas Digitalizadoras para desarrollo, virtualización o docencia.',
                'icono' => 'laptop',
                'orden' => 1,
                'criticidad_sugerida' => 'media',
            ],
            [
                'slug' => 'redes-telecomunicaciones',
                'nombre' => 'Redes y Telecomunicaciones',
                'descripcion' => 'Switches, Routers, Firewalls, Puntos de Acceso, Teléfonos IP y Certificadores de cableado.',
                'icono' => 'server',
                'orden' => 2,
                'criticidad_sugerida' => 'alta',
            ],
            [
                'slug' => 'instrumentacion-electronica',
                'nombre' => 'Instrumentación Electrónica y Sensores',
                'descripcion' => 'Osciloscopios, Multímetros digitales, Fuentes de poder regulables y Generadores de señales.',
                'icono' => 'activity',
                'orden' => 3,
                'criticidad_sugerida' => 'alta',
            ],
            [
                'slug' => 'iot-robotica',
                'nombre' => 'Sistemas Embebidos, IoT y Robótica',
                'descripcion' => 'Placas de desarrollo (Arduino, ESP32, Raspberry Pi, Jetson Nano), sensores y kits de robótica.',
                'icono' => 'cpu-chip',
                'orden' => 4,
                'criticidad_sugerida' => 'media',
            ],
            [
                'slug' => 'impresion-captura',
                'nombre' => 'Impresión, Captura y Punto de Venta (Auto-ID)',
                'descripcion' => 'Impresoras de etiquetas térmicas, impresoras 3D, impresoras de documentos y lectores de códigos de barra.',
                'icono' => 'printer',
                'orden' => 5,
                'criticidad_sugerida' => 'media',
            ],
            [
                'slug' => 'herramientas-taller',
                'nombre' => 'Herramientas de Taller y Redes',
                'descripcion' => 'Crimpadoras RJ45, ponchadoras, estaciones de soldadura y kits de herramientas de precisión.',
                'icono' => 'wrench',
                'orden' => 6,
                'criticidad_sugerida' => 'baja',
            ],
            [
                'slug' => 'audiovisual-vr',
                'nombre' => 'Audiovisual y Realidad Extendida',
                'descripcion' => 'Proyectores multimedia data show, cámaras web HD, micrófonos y visores de realidad virtual (VR).',
                'icono' => 'video-camera',
                'orden' => 7,
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

        // Ubicaciones de referencia
        $ubicacionPanol = Ubicacion::where('nombre', 'LIKE', '%Pañol%')->value('id') ?? Ubicacion::first()?->id ?? 1;
        $ubicacionLtel = Ubicacion::where('nombre', 'LTEL')->value('id') ?? Ubicacion::where('nombre', 'LIKE', '%Redes%')->value('id') ?? $ubicacionPanol;
        $ubicacionLinf = Ubicacion::where('nombre', 'LINF')->value('id') ?? Ubicacion::where('nombre', 'LIKE', '%Computación%')->value('id') ?? $ubicacionPanol;

        // Equipos a poblar/actualizar sin duplicar por código de barras
        $equipos = [
            // 1. Cómputo Móvil
            [
                'codigo_barras' => 'EQ-LNV-T14-01',
                'nombre' => 'Notebook Lenovo ThinkPad T14',
                'marca' => 'Lenovo',
                'modelo' => 'ThinkPad T14 Gen 3',
                'numero_serie' => 'SN-LNV-T14-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['computo-movil'],
            ],
            [
                'codigo_barras' => 'EQ-LNV-T14-02',
                'nombre' => 'Notebook Lenovo ThinkPad T14',
                'marca' => 'Lenovo',
                'modelo' => 'ThinkPad T14 Gen 3',
                'numero_serie' => 'SN-LNV-T14-002',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['computo-movil'],
            ],
            [
                'codigo_barras' => 'EQ-APL-MBP-01',
                'nombre' => 'MacBook Pro 14 Pulgadas Apple M3',
                'marca' => 'Apple',
                'modelo' => 'MacBook Pro 14',
                'numero_serie' => 'SN-APL-MBP-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLinf,
                'categoria_id' => $categoriasMap['computo-movil'],
            ],
            [
                'codigo_barras' => 'EQ-DEL-LAT-01',
                'nombre' => 'Notebook Dell Latitude 5440 Core i7',
                'marca' => 'Dell',
                'modelo' => 'Latitude 5440',
                'numero_serie' => 'SN-DEL-LAT-001',
                'criticidad' => 'media',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLinf,
                'categoria_id' => $categoriasMap['computo-movil'],
            ],
            [
                'codigo_barras' => 'EQ-LNV-TAB-01',
                'nombre' => 'Tablet Lenovo Tab M11 128GB Wi-Fi',
                'marca' => 'Lenovo',
                'modelo' => 'Tab M11',
                'numero_serie' => 'SN-LNV-TAB-001',
                'criticidad' => 'media',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionPanol,
                'categoria_id' => $categoriasMap['computo-movil'],
            ],
            [
                'codigo_barras' => 'EQ-SAM-TAB-01',
                'nombre' => 'Tablet Samsung Galaxy Tab S9 FE con S-Pen',
                'marca' => 'Samsung',
                'modelo' => 'Galaxy Tab S9 FE',
                'numero_serie' => 'SN-SAM-TAB-001',
                'criticidad' => 'media',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionPanol,
                'categoria_id' => $categoriasMap['computo-movil'],
            ],
            [
                'codigo_barras' => 'EQ-WAC-INT-01',
                'nombre' => 'Tableta Digitalizadora Wacom Intuos Pro Medium',
                'marca' => 'Wacom',
                'modelo' => 'Intuos Pro PTH-660',
                'numero_serie' => 'SN-WAC-INT-001',
                'criticidad' => 'media',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionPanol,
                'categoria_id' => $categoriasMap['computo-movil'],
            ],

            // 2. Redes y Telecomunicaciones
            [
                'codigo_barras' => 'EQ-CSC-2960-01',
                'nombre' => 'Switch Cisco Catalyst Capa 3 24 Puertos',
                'marca' => 'Cisco Systems',
                'modelo' => 'Catalyst 2960X-24TD-L',
                'numero_serie' => 'SN-CSC-2960-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['redes-telecomunicaciones'],
            ],
            [
                'codigo_barras' => 'EQ-ARU-2930-01',
                'nombre' => 'Switch Aruba 2930F 24 Puertos PoE+',
                'marca' => 'Aruba',
                'modelo' => '2930F JL253A',
                'numero_serie' => 'SN-ARU-2930-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['redes-telecomunicaciones'],
            ],
            [
                'codigo_barras' => 'EQ-CSC-ISR-01',
                'nombre' => 'Router Cisco ISR 4321 Gigabit Ethernet',
                'marca' => 'Cisco Systems',
                'modelo' => 'ISR 4321/K9',
                'numero_serie' => 'SN-CSC-ISR-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['redes-telecomunicaciones'],
            ],
            [
                'codigo_barras' => 'EQ-MIK-CCR-01',
                'nombre' => 'Router MikroTik Cloud Core Router 16G',
                'marca' => 'MikroTik',
                'modelo' => 'CCR2004-16G-2S+',
                'numero_serie' => 'SN-MIK-CCR-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['redes-telecomunicaciones'],
            ],
            [
                'codigo_barras' => 'EQ-UBI-U6P-01',
                'nombre' => 'Punto de Acceso Wi-Fi 6 Ubiquiti UniFi Pro',
                'marca' => 'Ubiquiti',
                'modelo' => 'UniFi 6 Pro',
                'numero_serie' => 'SN-UBI-U6P-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['redes-telecomunicaciones'],
            ],
            [
                'codigo_barras' => 'EQ-FTN-60F-01',
                'nombre' => 'Appliance Firewall Fortinet FortiGate 60F',
                'marca' => 'Fortinet',
                'modelo' => 'FortiGate 60F',
                'numero_serie' => 'SN-FTN-60F-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['redes-telecomunicaciones'],
            ],
            [
                'codigo_barras' => 'EQ-CSC-7821-01',
                'nombre' => 'Teléfono IP Cisco 7821 VoIP Dos Líneas',
                'marca' => 'Cisco Systems',
                'modelo' => 'CP-7821-K9',
                'numero_serie' => 'SN-CSC-7821-001',
                'criticidad' => 'media',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['redes-telecomunicaciones'],
            ],
            [
                'codigo_barras' => 'EQ-FLK-DSX-01',
                'nombre' => 'Certificador de Cableado Fluke Networks DSX-5000',
                'marca' => 'Fluke Networks',
                'modelo' => 'DSX-5000 CableAnalyzer',
                'numero_serie' => 'SN-FLK-DSX-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['redes-telecomunicaciones'],
            ],

            // 3. Instrumentación Electrónica
            [
                'codigo_barras' => 'EQ-TEK-1052-01',
                'nombre' => 'Osciloscopio Digital 2 Canales 50MHz',
                'marca' => 'Tektronix',
                'modelo' => 'TBS1052B-EDU',
                'numero_serie' => 'SN-TEK-1052-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['instrumentacion-electronica'],
            ],
            [
                'codigo_barras' => 'EQ-RIG-1054-01',
                'nombre' => 'Osciloscopio Digital 4 Canales 50MHz Rigol',
                'marca' => 'Rigol',
                'modelo' => 'DS1054Z',
                'numero_serie' => 'SN-RIG-1054-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['instrumentacion-electronica'],
            ],
            [
                'codigo_barras' => 'EQ-FLK-117-04',
                'nombre' => 'Multímetro Digital True RMS Fluke 117',
                'marca' => 'Fluke',
                'modelo' => '117 Electricians',
                'numero_serie' => 'SN-FLK-117-004',
                'criticidad' => 'media',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['instrumentacion-electronica'],
            ],
            [
                'codigo_barras' => 'EQ-UNI-T89-01',
                'nombre' => 'Multímetro Digital Tester Uni-T True RMS',
                'marca' => 'Uni-T',
                'modelo' => 'UT89X',
                'numero_serie' => 'SN-UNI-T89-001',
                'criticidad' => 'baja',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['instrumentacion-electronica'],
            ],
            [
                'codigo_barras' => 'EQ-KOR-3005-01',
                'nombre' => 'Fuente de Poder Regulable DC 30V 5A de Laboratorio',
                'marca' => 'Korad',
                'modelo' => 'KA3005D',
                'numero_serie' => 'SN-KOR-3005-001',
                'criticidad' => 'media',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['instrumentacion-electronica'],
            ],
            [
                'codigo_barras' => 'EQ-SIG-1032-01',
                'nombre' => 'Generador de Funciones y Formas de Onda 30MHz',
                'marca' => 'Siglent',
                'modelo' => 'SDG1032X',
                'numero_serie' => 'SN-SIG-1032-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLtel,
                'categoria_id' => $categoriasMap['instrumentacion-electronica'],
            ],

            // 4. Sistemas Embebidos, IoT y Robótica
            [
                'codigo_barras' => 'EQ-ARD-SEN-02',
                'nombre' => 'Kit Sensores Ultrasónicos y RFID para Docencia',
                'marca' => 'Arduino / Elegoo',
                'modelo' => 'Kit Sensores 37 en 1',
                'numero_serie' => 'SN-ARD-002',
                'criticidad' => 'baja',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLinf,
                'categoria_id' => $categoriasMap['iot-robotica'],
            ],
            [
                'codigo_barras' => 'EQ-ARD-UNO-01',
                'nombre' => 'Placa de Desarrollo Arduino Uno R3 ATmega328P',
                'marca' => 'Arduino',
                'modelo' => 'Uno R3 SMD',
                'numero_serie' => 'SN-ARD-UNO-001',
                'criticidad' => 'baja',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionPanol,
                'categoria_id' => $categoriasMap['iot-robotica'],
            ],
            [
                'codigo_barras' => 'EQ-ESP-W32-01',
                'nombre' => 'Módulo IoT Wi-Fi y Bluetooth ESP32 NodeMCU',
                'marca' => 'Espressif',
                'modelo' => 'ESP32-WROOM-32D',
                'numero_serie' => 'SN-ESP-W32-001',
                'criticidad' => 'baja',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionPanol,
                'categoria_id' => $categoriasMap['iot-robotica'],
            ],
            [
                'codigo_barras' => 'EQ-RPI-4B4-01',
                'nombre' => 'Microcomputador Raspberry Pi 4 Model B 4GB RAM',
                'marca' => 'Raspberry Pi',
                'modelo' => 'Raspberry Pi 4 4GB',
                'numero_serie' => 'SN-RPI-4B4-001',
                'criticidad' => 'media',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLinf,
                'categoria_id' => $categoriasMap['iot-robotica'],
            ],
            [
                'codigo_barras' => 'EQ-NV-JET-01',
                'nombre' => 'Placa de Inteligencia Artificial NVIDIA Jetson Nano',
                'marca' => 'NVIDIA',
                'modelo' => 'Jetson Nano 4GB B01',
                'numero_serie' => 'SN-NV-JET-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLinf,
                'categoria_id' => $categoriasMap['iot-robotica'],
            ],
            [
                'codigo_barras' => 'EQ-ROB-ARM-01',
                'nombre' => 'Brazo Robótico Educativo Programable 6 Grados',
                'marca' => 'Hiwonder',
                'modelo' => 'xArm 1S',
                'numero_serie' => 'SN-ROB-ARM-001',
                'criticidad' => 'media',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLinf,
                'categoria_id' => $categoriasMap['iot-robotica'],
            ],

            // 5. Impresión, Captura y Punto de Venta (Auto-ID)
            [
                'codigo_barras' => 'EQ-ZEB-220-01',
                'nombre' => 'Impresora Térmica de Etiquetas Zebra ZD220',
                'marca' => 'Zebra Technologies',
                'modelo' => 'ZD220 Direct Thermal',
                'numero_serie' => 'SN-ZEB-220-001',
                'criticidad' => 'media',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionPanol,
                'categoria_id' => $categoriasMap['impresion-captura'],
            ],
            [
                'codigo_barras' => 'EQ-BRO-QL8-01',
                'nombre' => 'Impresora de Etiquetas Profesional Brother QL-800',
                'marca' => 'Brother',
                'modelo' => 'QL-800',
                'numero_serie' => 'SN-BRO-QL8-001',
                'criticidad' => 'media',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionPanol,
                'categoria_id' => $categoriasMap['impresion-captura'],
            ],
            [
                'codigo_barras' => 'EQ-CRE-E3V-01',
                'nombre' => 'Impresora 3D de Prototipado Creality Ender 3 V3',
                'marca' => 'Creality',
                'modelo' => 'Ender 3 V3 SE',
                'numero_serie' => 'SN-CRE-E3V-001',
                'criticidad' => 'media',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLinf,
                'categoria_id' => $categoriasMap['impresion-captura'],
            ],
            [
                'codigo_barras' => 'EQ-BMB-P1S-01',
                'nombre' => 'Impresora 3D de Alta Velocidad Bambu Lab P1S',
                'marca' => 'Bambu Lab',
                'modelo' => 'P1S Combo',
                'numero_serie' => 'SN-BMB-P1S-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLinf,
                'categoria_id' => $categoriasMap['impresion-captura'],
            ],
            [
                'codigo_barras' => 'EQ-ZEB-DS2-01',
                'nombre' => 'Lector de Códigos de Barra 1D y 2D Zebra DS2208',
                'marca' => 'Zebra Technologies',
                'modelo' => 'DS2208 Handheld Scanner',
                'numero_serie' => 'SN-ZEB-DS2-001',
                'criticidad' => 'baja',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionPanol,
                'categoria_id' => $categoriasMap['impresion-captura'],
            ],

            // 6. Herramientas de Taller y Redes
            [
                'codigo_barras' => 'EQ-CRIMP-01',
                'nombre' => 'Crimpadora Modular RJ45 RJ11 Profesional y Pelacables',
                'marca' => 'Klein Tools',
                'modelo' => 'VDV226-011-SEN',
                'numero_serie' => 'SN-CRIMP-001',
                'criticidad' => 'baja',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionPanol,
                'categoria_id' => $categoriasMap['herramientas-taller'],
            ],
            [
                'codigo_barras' => 'EQ-HAK-FX8-01',
                'nombre' => 'Estación de Soldadura Digital Antiestática ESD',
                'marca' => 'Hakko',
                'modelo' => 'FX-888D',
                'numero_serie' => 'SN-HAK-FX8-001',
                'criticidad' => 'media',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionPanol,
                'categoria_id' => $categoriasMap['herramientas-taller'],
            ],
            [
                'codigo_barras' => 'EQ-IFX-PRO-01',
                'nombre' => 'Kit de Herramientas de Precisión 64 Puntas para Electrónica',
                'marca' => 'iFixit',
                'modelo' => 'Pro Tech Toolkit',
                'numero_serie' => 'SN-IFX-PRO-001',
                'criticidad' => 'baja',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionPanol,
                'categoria_id' => $categoriasMap['herramientas-taller'],
            ],

            // 7. Audiovisual y Realidad Extendida
            [
                'codigo_barras' => 'EQ-EPS-DAT-01',
                'nombre' => 'Proyector Data Show Portátil 3LCD 3600 Lúmenes',
                'marca' => 'Epson',
                'modelo' => 'PowerLite E20',
                'numero_serie' => 'SN-EPS-DAT-001',
                'criticidad' => 'media',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionPanol,
                'categoria_id' => $categoriasMap['audiovisual-vr'],
            ],
            [
                'codigo_barras' => 'EQ-LOG-C92-01',
                'nombre' => 'Cámara Web Full HD 1080p con Micrófono Estéreo',
                'marca' => 'Logitech',
                'modelo' => 'C920 HD Pro',
                'numero_serie' => 'SN-LOG-C92-001',
                'criticidad' => 'baja',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionPanol,
                'categoria_id' => $categoriasMap['audiovisual-vr'],
            ],
            [
                'codigo_barras' => 'EQ-MET-QST-01',
                'nombre' => 'Visor de Realidad Virtual y Mixta Meta Quest 3 128GB',
                'marca' => 'Meta',
                'modelo' => 'Quest 3',
                'numero_serie' => 'SN-MET-QST-001',
                'criticidad' => 'alta',
                'estado' => 'disponible',
                'ubicacion_id' => $ubicacionLinf,
                'categoria_id' => $categoriasMap['audiovisual-vr'],
            ],
        ];

        foreach ($equipos as $eqData) {
            Equipo::updateOrCreate(
                ['codigo_barras' => $eqData['codigo_barras']],
                $eqData
            );
        }

        // Historial de Mantenimiento para EQ-LNV-T14-01 (coincidente con Mockup 3)
        $lenovoT14 = Equipo::where('codigo_barras', 'EQ-LNV-T14-01')->first();
        if ($lenovoT14) {
            $proveedor = Proveedor::first();

            // Mantenimiento 1 (Preventivo realizado)
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

            // Mantenimiento 2 (Correctivo realizado)
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

            // Mantenimiento 3 (Preventivo reciente)
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

            // Mantenimiento 4 (Preventivo futuro programado)
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
