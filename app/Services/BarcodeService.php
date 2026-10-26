<?php

namespace App\Services;

use App\Models\Equipo;
use App\Models\Insumo;
use InvalidArgumentException;

class BarcodeService
{
    /**
     * Code 128 patterns (widths of bars and spaces) for symbols 0..106.
     * Each pattern has 6 widths summing to 11 (stop symbol 106 has 7 widths summing to 13).
     *
     * @var array<int, string>
     */
    protected const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '80' => '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '90' => '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '100' => '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    protected const START_B = 104;
    protected const STOP = 106;

    /**
     * Genera la representación en barras (módulos binarios '1' barra, '0' espacio) para CODE128 Subset B.
     */
    public function codificarCode128B(string $texto): string
    {
        if (trim($texto) === '') {
            throw new InvalidArgumentException('El texto a codificar no puede estar vacío.');
        }

        $longitud = strlen($texto);
        $valores = [self::START_B];
        $sumaCheck = self::START_B;

        for ($i = 0; $i < $longitud; $i++) {
            $ascii = ord($texto[$i]);
            if ($ascii < 32 || $ascii > 126) {
                throw new InvalidArgumentException("Carácter no admitido en CODE128-B: '{$texto[$i]}'");
            }
            $valor = $ascii - 32;
            $valores[] = $valor;
            $sumaCheck += $valor * ($i + 1);
        }

        $checkDigit = $sumaCheck % 103;
        $valores[] = $checkDigit;
        $valores[] = self::STOP;

        // Construir string de módulos 1 (barra) y 0 (espacio)
        // Reindex patterns consistently
        $patterns = array_values(self::PATTERNS);
        $modulos = '0000000000'; // Margen de seguridad inicial (quiet zone)

        foreach ($valores as $val) {
            $pattern = $patterns[$val];
            $isBar = true;
            for ($k = 0; $k < strlen($pattern); $k++) {
                $width = (int) $pattern[$k];
                $modulos .= str_repeat($isBar ? '1' : '0', $width);
                $isBar = ! $isBar;
            }
        }

        $modulos .= '0000000000'; // Margen de seguridad final (quiet zone)

        return $modulos;
    }

    /**
     * Genera un SVG lineal de código de barras CODE128.
     */
    public function generarSvg(string $texto, int $altura = 60, int $anchoModulo = 2): string
    {
        $modulos = $this->codificarCode128B($texto);
        $totalModulos = strlen($modulos);
        $anchoTotal = $totalModulos * $anchoModulo;

        $rectangulos = '';
        $x = 0;
        $i = 0;

        while ($i < $totalModulos) {
            if ($modulos[$i] === '1') {
                $anchoBarra = 0;
                $inicioX = $x;
                while ($i < $totalModulos && $modulos[$i] === '1') {
                    $anchoBarra += $anchoModulo;
                    $x += $anchoModulo;
                    $i++;
                }
                $rectangulos .= "<rect x=\"{$inicioX}\" y=\"0\" width=\"{$anchoBarra}\" height=\"{$altura}\" fill=\"#000000\"/>";
            } else {
                $x += $anchoModulo;
                $i++;
            }
        }

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$anchoTotal} {$altura}" width="{$anchoTotal}" height="{$altura}" shape-rendering="crispEdges">
    <rect width="100%" height="100%" fill="#ffffff"/>
    {$rectangulos}
</svg>
SVG;
    }

    /**
     * Genera una etiqueta completa lista para imprimir con el formato institucional descriptivo.
     *
     * @param array{codigo: string, nombre: string, institucion?: string, subtitulo?: string|null, ubicacion?: string|null} $datos
     */
    public function generarEtiquetaHtml(array $datos): string
    {
        $codigo = htmlspecialchars($datos['codigo'], ENT_QUOTES, 'UTF-8');
        $nombre = htmlspecialchars($datos['nombre'], ENT_QUOTES, 'UTF-8');
        $institucion = htmlspecialchars($datos['institucion'] ?? 'INACAP · SEDE TEMUCO', ENT_QUOTES, 'UTF-8');
        $ubicacion = htmlspecialchars($datos['ubicacion'] ?? 'Pañol Área Informática', ENT_QUOTES, 'UTF-8');
        $svg = $this->generarSvg($datos['codigo'], 50, 2);

        return <<<HTML
<div class="etiqueta-institucional" style="display:inline-block;border:2px solid #b91c1c;padding:12px 16px;border-radius:6px;background:#ffffff;font-family:Arial,sans-serif;max-width:320px;text-align:center;box-sizing:border-box;margin:6px;">
    <div style="font-size:11px;font-weight:bold;color:#b91c1c;letter-spacing:1px;text-transform:uppercase;margin-bottom:4px;">
        {$institucion}
    </div>
    <div style="font-size:12px;font-weight:bold;color:#1e293b;line-height:1.2;margin-bottom:6px;min-height:28px;">
        {$nombre}
    </div>
    <div style="display:flex;justify-content:center;margin-bottom:4px;">
        {$svg}
    </div>
    <div style="font-size:13px;font-weight:bold;font-family:monospace;color:#0f172a;letter-spacing:2px;">
        *{$codigo}*
    </div>
    <div style="font-size:9px;color:#64748b;margin-top:4px;">
        Ubicación: {$ubicacion}
    </div>
</div>
HTML;
    }

    /**
     * Genera un lote de hasta 20 etiquetas optimizado para impresión (cumpliendo SLA-07: <= 10 seg).
     *
     * @param array<int, array{codigo: string, nombre: string, institucion?: string, ubicacion?: string|null}> $items
     * @return array{tiempo_ms: float, total: int, html: string}
     */
    public function generarLoteEtiquetas(array $items): array
    {
        if (count($items) > 20) {
            throw new InvalidArgumentException('El tamaño máximo de lote institucional es de 20 etiquetas por tirada (SLA-07).');
        }

        $inicio = microtime(true);
        $htmlLote = '<div class="contenedor-lote-etiquetas" style="display:flex;flex-wrap:wrap;gap:8px;padding:16px;">';

        foreach ($items as $item) {
            $htmlLote .= $this->generarEtiquetaHtml($item);
        }

        $htmlLote .= '</div>';
        $fin = microtime(true);
        $tiempoMs = ($fin - $inicio) * 1000;

        return [
            'tiempo_ms' => round($tiempoMs, 2),
            'total' => count($items),
            'html' => $htmlLote,
        ];
    }

    /**
     * Busca un equipo o insumo mediante la lectura de código de barras (CP-12).
     *
     * @return array{tipo: 'equipo'|'insumo', item: Equipo|Insumo}|null
     */
    public function buscarPorCodigoBarras(string $codigo): ?array
    {
        $codigoLimpio = trim($codigo);

        if ($codigoLimpio === '') {
            return null;
        }

        $equipo = Equipo::with('ubicacion')->where('codigo_barras', $codigoLimpio)->first();
        if ($equipo) {
            return ['tipo' => 'equipo', 'item' => $equipo];
        }

        $insumo = Insumo::with('ubicacion')->where('codigo_barras', $codigoLimpio)->first();
        if ($insumo) {
            return ['tipo' => 'insumo', 'item' => $insumo];
        }

        return null;
    }
}
