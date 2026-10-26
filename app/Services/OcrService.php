<?php

namespace App\Services;

use App\Exceptions\DocumentoDuplicadoException;
use App\Models\Compra;
use App\Models\Documento;
use App\Models\Proveedor;
use Illuminate\Http\UploadedFile;
use Throwable;

class OcrService
{
    /**
     * Calcula el hash SHA-256 de un archivo físico o subido (CP-10).
     */
    public function calcularHash(UploadedFile|string $archivo): string
    {
        $path = $archivo instanceof UploadedFile ? $archivo->getRealPath() : $archivo;

        if (! file_exists($path)) {
            // Si es un contenido binario o de texto crudo en string
            return hash('sha256', $path);
        }

        return hash_file('sha256', $path);
    }

    /**
     * Valida que el documento no haya sido previamente procesado en el sistema (CP-10).
     * Detección por hash criptográfico SHA-256 y por par emisor (proveedor) + número de documento.
     *
     * @throws DocumentoDuplicadoException
     */
    public function validarNoDuplicado(
        string $hashSha256,
        ?int $proveedorId = null,
        ?string $numeroDocumento = null
    ): void {
        // Detección 1: Duplicidad por huella digital del archivo
        $documentoExistente = Documento::where('hash_sha256', $hashSha256)->first();
        if ($documentoExistente) {
            throw new DocumentoDuplicadoException(
                "Documento duplicado: El archivo ya fue registrado previamente en el sistema (ID Documento: #{$documentoExistente->id}, Tipo: {$documentoExistente->tipo}, Nombre: {$documentoExistente->nombre_original}). Se bloquea el reingreso para evitar duplicación de costos."
            );
        }

        // Detección 2: Duplicidad por folio fiscal y emisor
        if ($proveedorId && $numeroDocumento) {
            $compraExistente = Compra::where('proveedor_id', $proveedorId)
                ->where('numero_documento', trim($numeroDocumento))
                ->first();

            if ($compraExistente) {
                $proveedor = Proveedor::find($proveedorId);
                $emisor = $proveedor ? $proveedor->razon_social : "Proveedor #{$proveedorId}";
                throw new DocumentoDuplicadoException(
                    "Compra ya registrada: El documento N° {$numeroDocumento} del emisor '{$emisor}' ya existe en el sistema (Compra #{$compraExistente->id}). Se evita registrar dos veces la misma adquisición."
                );
            }
        }
    }

    /**
     * Adaptador para extracción documental mediante OCR (CP-08).
     * Procesa texto plano, JSON o documentos simulados extrayendo metadatos y líneas de detalle.
     * En caso de falla o calidad deficiente, maneja el incidente de forma controlada (PI-05, SLA-04).
     *
     * @return array{
     *     ocr_exitoso: bool,
     *     confianza: float,
     *     error: ?string,
     *     numero_documento: ?string,
     *     proveedor_rut: ?string,
     *     proveedor_nombre: ?string,
     *     fecha: ?string,
     *     monto_total: ?float,
     *     items: array<int, array{
     *         descripcion: string,
     *         cantidad: float,
     *         costo_unitario: float,
     *         impuesto: float
     *     }>
     * }
     */
    public function extraerDatos(UploadedFile|string $archivo): array
    {
        try {
            $contenido = '';
            if ($archivo instanceof UploadedFile) {
                $contenido = file_get_contents($archivo->getRealPath());
            } elseif (file_exists($archivo)) {
                $contenido = file_get_contents($archivo);
            } else {
                $contenido = (string) $archivo;
            }

            // Si el contenido viene formateado como JSON estructurado (ej. respuesta de un motor OCR cloud o simulador)
            if (str_starts_with(trim($contenido), '{')) {
                $json = json_decode($contenido, true);
                if (is_array($json) && isset($json['numero_documento'])) {
                    return [
                        'ocr_exitoso' => true,
                        'confianza' => (float) ($json['confianza'] ?? 0.95),
                        'error' => null,
                        'numero_documento' => (string) $json['numero_documento'],
                        'proveedor_rut' => $json['proveedor_rut'] ?? null,
                        'proveedor_nombre' => $json['proveedor_nombre'] ?? null,
                        'fecha' => $json['fecha'] ?? date('Y-m-d'),
                        'monto_total' => isset($json['monto_total']) ? (float) $json['monto_total'] : null,
                        'items' => $this->normalizarItems($json['items'] ?? []),
                    ];
                }
            }

            // Extracción heurística mediante expresiones regulares sobre el texto escaneado
            return $this->parsearTextoEscaneado($contenido);
        } catch (Throwable $e) {
            // Tolerancia a fallas: notificación de incidente sin caída del sistema (PI-05)
            return [
                'ocr_exitoso' => false,
                'confianza' => 0.0,
                'error' => 'No fue posible procesar el documento automáticamente. El pañolero puede ingresar los datos manualmente.',
                'numero_documento' => null,
                'proveedor_rut' => null,
                'proveedor_nombre' => null,
                'fecha' => date('Y-m-d'),
                'monto_total' => 0.0,
                'items' => [],
            ];
        }
    }

    /**
     * Parsea texto libre escaneado de facturas u órdenes de compra chilenas.
     *
     * @return array{
     *     ocr_exitoso: bool,
     *     confianza: float,
     *     error: ?string,
     *     numero_documento: ?string,
     *     proveedor_rut: ?string,
     *     proveedor_nombre: ?string,
     *     fecha: ?string,
     *     monto_total: ?float,
     *     items: array<int, array{
     *         descripcion: string,
     *         cantidad: float,
     *         costo_unitario: float,
     *         impuesto: float
     *     }>
     * }
     */
    protected function parsearTextoEscaneado(string $texto): array
    {
        $numeroDocumento = null;
        $proveedorRut = null;
        $proveedorNombre = null;
        $fecha = null;
        $montoTotal = null;
        $items = [];

        // Detección de Folio / Número de Factura u Orden de Compra
        if (preg_match('/(?:FACTURA|ORDEN\s+DE\s+COMPRA|FOLIO|DOCUMENTO|N[°ºo])\b[^\d\n:]*?[:#\-]\s*([A-Za-z0-9\-]+)/iu', $texto, $m)) {
            $numeroDocumento = trim($m[1]);
        }

        // Detección de RUT chileno (ej: 76.123.456-7 o 76123456-7)
        if (preg_match('/(?:RUT|R\.U\.T\.)\s*[:\.]?\s*(\d{1,2}\.?\d{3}\.?\d{3}-[\dkK])/i', $texto, $m)) {
            $proveedorRut = trim($m[1]);
        }

        // Detección de Fecha (YYYY-MM-DD o DD/MM/YYYY)
        if (preg_match('/(?:FECHA|EMISION)\s*[:\.]?\s*(\d{4}-\d{2}-\d{2})/i', $texto, $m)) {
            $fecha = $m[1];
        } elseif (preg_match('/(?:FECHA|EMISION)\s*[:\.]?\s*(\d{2})[\/\-](\d{2})[\/\-](\d{4})/i', $texto, $m)) {
            $fecha = "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        // Detección de Monto Total
        if (preg_match('/(?:TOTAL|TOTAL A PAGAR|MONTO TOTAL)\s*[:\$]?\s*([\d\.,]+)/i', $texto, $m)) {
            $montoLimpio = str_replace(['.', '$', ' '], '', $m[1]);
            $montoLimpio = str_replace(',', '.', $montoLimpio);
            $montoTotal = (float) $montoLimpio;
        }

        // Detección de líneas de ítem: formato típico "ITEM | CANT | PRECIO" o "DESCRIPCION x CANTIDAD $PRECIO"
        $lineas = explode("\n", $texto);
        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if (preg_match('/^([A-Za-z0-9\s\.\-_]+?)\s*[,;\t|]\s*(\d+(?:\.\d+)?)\s*[,;\t|]\s*([\d\.,]+)/', $linea, $m)) {
                $desc = trim($m[1]);
                $cant = (float) $m[2];
                $precio = (float) str_replace(['.', '$', ' '], '', $m[3]);
                if ($cant > 0 && $precio > 0) {
                    $items[] = [
                        'descripcion' => $desc,
                        'cantidad' => $cant,
                        'costo_unitario' => $precio,
                        'impuesto' => 0.0,
                    ];
                }
            }
        }

        $exitoso = ($numeroDocumento !== null);

        return [
            'ocr_exitoso' => $exitoso,
            'confianza' => $exitoso ? 0.90 : 0.40,
            'error' => $exitoso ? null : 'Calidad de imagen o formato insuficiente para extraer número de documento.',
            'numero_documento' => $numeroDocumento,
            'proveedor_rut' => $proveedorRut,
            'proveedor_nombre' => $proveedorNombre,
            'fecha' => $fecha ?? date('Y-m-d'),
            'monto_total' => $montoTotal,
            'items' => $items,
        ];
    }

    /**
     * @param  array<int, mixed>  $rawItems
     * @return array<int, array{descripcion: string, cantidad: float, costo_unitario: float, impuesto: float}>
     */
    protected function normalizarItems(array $rawItems): array
    {
        $items = [];
        foreach ($rawItems as $item) {
            if (! is_array($item)) {
                continue;
            }

            $items[] = [
                'descripcion' => (string) ($item['descripcion'] ?? 'Ítem sin descripción'),
                'cantidad' => max(0.01, (float) ($item['cantidad'] ?? 1)),
                'costo_unitario' => max(0.0, (float) ($item['costo_unitario'] ?? 0)),
                'impuesto' => max(0.0, (float) ($item['impuesto'] ?? 0)),
            ];
        }

        return $items;
    }
}
