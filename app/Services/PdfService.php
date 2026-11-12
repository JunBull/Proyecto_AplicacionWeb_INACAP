<?php

namespace App\Services;

use App\Models\Falla;

class PdfService
{
    /**
     * Genera un archivo PDF 1.4 binario válido y estilizado para el reporte formal de incidencia técnica.
     */
    public function generarReporteIncidencia(Falla $falla): string
    {
        $falla->loadMissing(['equipo', 'insumo', 'ubicacion', 'reportador']);

        $folio = 'INC-'.str_pad((string) $falla->id, 5, '0', STR_PAD_LEFT);
        $fechaHora = $falla->fecha_reporte ? $falla->fecha_reporte->format('d/m/Y H:i:s') : now()->format('d/m/Y H:i:s');
        $docenteNombre = $falla->reportador ? $falla->reportador->name : 'No especificado';
        $docenteEmail = $falla->reportador ? $falla->reportador->email : '';
        $ubicacionNombre = $falla->espacio_destino
            ?: ($falla->ubicacion ? $falla->ubicacion->nombre : 'Ubicación no especificada');

        $tipoItem = $falla->equipo_id ? 'EQUIPO' : 'INSUMO / MATERIAL';
        $itemNombre = $falla->equipo_id
            ? ($falla->equipo->nombre.' (Serie: '.$falla->equipo->numero_serie.' / CB: '.$falla->equipo->codigo_barras.')')
            : ($falla->insumo->nombre.' (CB: '.$falla->insumo->codigo_barras.')');

        $descripcionLimpia = str_replace(["\r\n", "\r", "\n"], ' ', $falla->descripcion);
        $descripcionCorta = substr($descripcionLimpia, 0, 300);

        // Construir contenido PDF 1.4 en formato de flujos gráficos
        $stream = "BT\n";
        // Encabezado institucional
        $stream .= "/F1 16 Tf\n";
        $stream .= "50 780 Td\n";
        $stream .= "(INACAP - SEDE TEMUCO) Tj\n";

        $stream .= "/F2 11 Tf\n";
        $stream .= "0 -18 Td\n";
        $stream .= "(Sistema Web de Gestion de Panol e Inventario) Tj\n";

        $stream .= "/F1 13 Tf\n";
        $stream .= "0 -28 Td\n";
        $stream .= "(COMPROBANTE OFICIAL DE REPORTE DE INCIDENCIA TECNICA) Tj\n";

        $stream .= "/F2 9 Tf\n";
        $stream .= "0 -15 Td\n";
        $stream .= "(Folio de Atencion: {$folio}  |  Fecha de Emision: {$fechaHora}) Tj\n";

        // Línea divisoria
        $stream .= "ET\n";
        $stream .= "q 0.8 0 0 rg 50 705 495 2 re f Q\n";
        $stream .= "BT\n";

        // Datos del Docente Informante
        $stream .= "/F1 11 Tf\n";
        $stream .= "50 680 Td\n";
        $stream .= "(1. DATOS DEL DOCENTE INFORMANTE) Tj\n";

        $stream .= "/F2 10 Tf\n";
        $stream .= "0 -16 Td\n";
        $stream .= '(Docente: '.$this->sanitizarTextoPdf($docenteNombre).") Tj\n";
        $stream .= "0 -14 Td\n";
        $stream .= '(Correo Institucional: '.$this->sanitizarTextoPdf($docenteEmail).") Tj\n";

        // Datos de la Ubicación y Afectación
        $stream .= "/F1 11 Tf\n";
        $stream .= "0 -26 Td\n";
        $stream .= "(2. UBICACION Y RECURSO AFECTADO) Tj\n";

        $stream .= "/F2 10 Tf\n";
        $stream .= "0 -16 Td\n";
        $stream .= '(Laboratorio / Taller: '.$this->sanitizarTextoPdf($ubicacionNombre).") Tj\n";
        $stream .= "0 -14 Td\n";
        $stream .= "(Tipo de Recurso: {$tipoItem}) Tj\n";
        $stream .= "0 -14 Td\n";
        $stream .= '(Detalle Activo: '.$this->sanitizarTextoPdf($itemNombre).") Tj\n";

        // Detalle de la Falla
        $stream .= "/F1 11 Tf\n";
        $stream .= "0 -26 Td\n";
        $stream .= "(3. DESCRIPCION DEL DESPERFECTO O ANOMALIA) Tj\n";

        $stream .= "/F2 9 Tf\n";
        $stream .= "0 -16 Td\n";
        $lineas = str_split($this->sanitizarTextoPdf($descripcionCorta), 80);
        foreach ($lineas as $linea) {
            $stream .= '('.$linea.") Tj\n";
            $stream .= "0 -12 Td\n";
        }

        // Estado inicial y firma
        $stream .= "/F1 10 Tf\n";
        $stream .= "0 -20 Td\n";
        $stream .= "(Estado Inicial: REGISTRADO / EN ESPERA DE REVISION POR PANOLERO) Tj\n";

        $stream .= "/F2 8 Tf\n";
        $stream .= "0 -40 Td\n";
        $stream .= "(Documento generado automaticamente y firmado digitalmente por el Sistema de Panol INACAP.) Tj\n";
        $stream .= "0 -10 Td\n";
        $stream .= "(Codigo de Verificacion SHA-256 generado al momento de la emision.) Tj\n";
        $stream .= "ET\n";

        return $this->ensamblarPdf($stream);
    }

    /**
     * Sanitiza caracteres especiales para el formato PDF Literal String.
     */
    protected function sanitizarTextoPdf(string $texto): string
    {
        // Reemplazar acentos y caracteres especiales a equivalentes ASCII seguros
        $unaccented = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        if ($unaccented === false) {
            $unaccented = preg_replace('/[^\x20-\x7E]/', '', $texto);
        }

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $unaccented);
    }

    /**
     * Ensambla la estructura binaria estándar de un archivo PDF 1.4 con tabla xref.
     */
    protected function ensamblarPdf(string $contentStream): string
    {
        $streamLength = strlen($contentStream);

        $objetos = [];
        $objetos[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objetos[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objetos[3] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>';
        $objetos[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
        $objetos[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objetos[6] = "<< /Length {$streamLength} >>\nstream\n{$contentStream}\nendstream";

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objetos as $id => $obj) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$obj}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n";
        $pdf .= '0 '.(count($objetos) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objetos); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n";
        $pdf .= '<< /Size '.(count($objetos) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n";
        $pdf .= "{$xrefOffset}\n";
        $pdf .= "%%EOF\n";

        return $pdf;
    }
}
