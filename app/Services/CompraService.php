<?php

namespace App\Services;

use App\Models\Carrera;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\Documento;
use App\Models\Insumo;
use App\Models\MovimientoInventario;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompraService
{
    public function __construct(
        protected PresupuestoService $presupuestoService,
        protected OcrService $ocrService,
        protected InventarioService $inventarioService
    ) {}

    /**
     * Confirma una compra e ingresa sus ítems al inventario con descuento presupuestario atómico (CP-09, CP-15, PI-01, PI-04).
     *
     * @param array{
     *     proveedor_id: int,
     *     carrera_id: int,
     *     numero_documento: string,
     *     fecha: string,
     *     adjudicacion_id?: ?int,
     *     tipo_documento?: 'factura'|'orden_compra'
     * } $datosCompra
     * @param array<int, array{
     *     insumo_id?: ?int,
     *     descripcion: string,
     *     cantidad: float,
     *     costo_unitario: float,
     *     impuesto?: float
     * }> $detalles
     */
    public function registrarCompraEIngreso(
        array $datosCompra,
        array $detalles,
        User $usuario,
        ?UploadedFile $archivoDocumento = null
    ): Compra {
        $this->validarDatosCompra($datosCompra, $detalles);

        $hashSha256 = null;
        if ($archivoDocumento) {
            $hashSha256 = $this->ocrService->calcularHash($archivoDocumento);
            // CP-10: Detección preventiva de duplicidad de documentos
            $this->ocrService->validarNoDuplicado(
                $hashSha256,
                $datosCompra['proveedor_id'],
                $datosCompra['numero_documento']
            );
        } else {
            // Verificar también por combinación de proveedor y número de documento
            $this->ocrService->validarNoDuplicado(
                hash('sha256', "prov:{$datosCompra['proveedor_id']}-doc:{$datosCompra['numero_documento']}"),
                $datosCompra['proveedor_id'],
                $datosCompra['numero_documento']
            );
        }

        /** @var Carrera $carrera */
        $carrera = Carrera::findOrFail($datosCompra['carrera_id']);

        // Calcular monto total acumulado de las líneas
        $montoTotal = 0.0;
        foreach ($detalles as $linea) {
            $subtotal = ($linea['cantidad'] * $linea['costo_unitario']) + ($linea['impuesto'] ?? 0.0);
            $montoTotal += $subtotal;
        }

        // Ejecución transaccional atómica obligatoria (PI-01 y Regla GEMINI)
        return DB::transaction(function () use ($datosCompra, $detalles, $usuario, $archivoDocumento, $hashSha256, $carrera, $montoTotal) {
            // Paso 1: Descuento presupuestario atómico con bloqueo pesimista lockForUpdate() (CP-15, PI-04)
            $anio = (int) date('Y', strtotime($datosCompra['fecha']));
            $movimientoPresupuesto = $this->presupuestoService->descontarPresupuesto(
                $carrera,
                $montoTotal,
                $usuario,
                null,
                $anio
            );

            // Paso 2: Creación de la cabecera de Compra
            /** @var Compra $compra */
            $compra = Compra::create([
                'adjudicacion_id' => $datosCompra['adjudicacion_id'] ?? null,
                'proveedor_id' => $datosCompra['proveedor_id'],
                'carrera_id' => $carrera->id,
                'numero_documento' => trim($datosCompra['numero_documento']),
                'fecha' => $datosCompra['fecha'],
                'monto_total' => $montoTotal,
                'estado' => 'ingresada_inventario',
            ]);

            // Vincular el movimiento presupuestario a la compra registrada
            $movimientoPresupuesto->update(['compra_id' => $compra->id]);

            // Paso 3: Guardar líneas de detalle y actualizar existencias en inventario (CP-09, PI-01)
            foreach ($detalles as $linea) {
                $insumoId = $linea['insumo_id'] ?? null;
                $cantidad = (float) $linea['cantidad'];
                $costoUnitario = (float) $linea['costo_unitario'];
                $impuesto = (float) ($linea['impuesto'] ?? 0.0);

                CompraDetalle::create([
                    'compra_id' => $compra->id,
                    'insumo_id' => $insumoId,
                    'descripcion' => trim($linea['descripcion']),
                    'cantidad' => $cantidad,
                    'costo_unitario' => $costoUnitario,
                    'impuesto' => $impuesto,
                ]);

                // Si la línea está asociada a un insumo existente en catálogo, actualizar stock con bloqueo pesimista
                if ($insumoId) {
                    /** @var Insumo $insumoBloqueado */
                    $insumoBloqueado = Insumo::where('id', $insumoId)->lockForUpdate()->firstOrFail();
                    $insumoBloqueado->stock_actual = (float) $insumoBloqueado->stock_actual + $cantidad;
                    $insumoBloqueado->save();

                    // Registrar en bitácora inmutable de Kardex
                    MovimientoInventario::create([
                        'insumo_id' => $insumoBloqueado->id,
                        'usuario_id' => $usuario->id,
                        'compra_id' => $compra->id,
                        'solicitud_id' => null,
                        'tipo' => 'ingreso_compra',
                        'cantidad' => $cantidad,
                        'fecha' => now(),
                    ]);
                }
            }

            // Paso 4: Almacenar respaldo digital del documento con su hash SHA-256 (CP-10)
            if ($archivoDocumento && $hashSha256) {
                $tipoDoc = $datosCompra['tipo_documento'] ?? 'factura';
                $rutaAlmacenada = $archivoDocumento->store('documentos/compras', 'local');

                Documento::create([
                    'tipo' => $tipoDoc,
                    'ruta' => $rutaAlmacenada,
                    'nombre_original' => $archivoDocumento->getClientOriginalName(),
                    'hash_sha256' => $hashSha256,
                    'documentable_type' => Compra::class,
                    'documentable_id' => $compra->id,
                    'subido_por' => $usuario->id,
                    'creado_en' => now(),
                ]);
            }

            return $compra->load([
                'proveedor',
                'carrera',
                'detalles.insumo',
                'movimientosPresupuesto',
                'movimientosInventario',
                'documentos',
            ]);
        });
    }

    /**
     * Lista compras registradas con filtros.
     */
    public function listarCompras(?int $carreraId = null): Collection
    {
        $query = Compra::with(['proveedor', 'carrera', 'detalles.insumo', 'documentos'])
            ->latest('fecha');

        if ($carreraId) {
            $query->where('carrera_id', $carreraId);
        }

        return $query->get();
    }

    /**
     * Valida los datos requeridos para la cabecera e ítems de compra.
     *
     * @param  array<string, mixed>  $datosCompra
     * @param  array<int, mixed>  $detalles
     */
    protected function validarDatosCompra(array $datosCompra, array $detalles): void
    {
        $errores = [];

        if (empty($datosCompra['proveedor_id']) || ! Proveedor::where('id', $datosCompra['proveedor_id'])->exists()) {
            $errores['proveedor_id'] = 'El proveedor es obligatorio y debe existir registrado en el sistema.';
        }

        if (empty($datosCompra['carrera_id']) || ! Carrera::where('id', $datosCompra['carrera_id'])->exists()) {
            $errores['carrera_id'] = 'La carrera asignada es obligatoria y debe existir.';
        }

        if (empty($datosCompra['numero_documento']) || trim($datosCompra['numero_documento']) === '') {
            $errores['numero_documento'] = 'El número o folio del documento de compra es obligatorio.';
        }

        if (empty($datosCompra['fecha'])) {
            $errores['fecha'] = 'La fecha de la compra es obligatoria.';
        }

        if (empty($detalles)) {
            $errores['detalles'] = 'La compra debe contener al menos una línea de ítem o insumo recibido.';
        }

        foreach ($detalles as $i => $item) {
            if (empty($item['descripcion']) || trim($item['descripcion']) === '') {
                $errores["detalles.{$i}.descripcion"] = 'La descripción del ítem en la fila '.($i + 1).' es obligatoria.';
            }

            if (! isset($item['cantidad']) || (float) $item['cantidad'] <= 0) {
                $errores["detalles.{$i}.cantidad"] = 'La cantidad de la fila '.($i + 1).' debe ser superior a cero.';
            }

            if (! isset($item['costo_unitario']) || (float) $item['costo_unitario'] < 0) {
                $errores["detalles.{$i}.costo_unitario"] = 'El costo unitario de la fila '.($i + 1).' no puede ser negativo.';
            }
        }

        if (! empty($errores)) {
            throw ValidationException::withMessages($errores);
        }
    }
}
