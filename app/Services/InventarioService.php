<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\Insumo;
use App\Models\MovimientoInventario;
use App\Models\SolicitudMaterial;
use App\Models\Ubicacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class InventarioService
{
    /**
     * Registra un nuevo insumo con su ubicación física detallada (CP-11).
     *
     * @param array{
     *     codigo_barras: string,
     *     nombre: string,
     *     unidad: string,
     *     es_retornable?: bool,
     *     stock_actual?: float|int,
     *     stock_minimo?: float|int,
     *     ubicacion_id: int
     * } $datos
     */
    public function registrarInsumo(array $datos): Insumo
    {
        $this->validarDatosInsumo($datos);

        return Insumo::create([
            'codigo_barras' => trim($datos['codigo_barras']),
            'nombre' => trim($datos['nombre']),
            'unidad' => trim($datos['unidad']),
            'es_retornable' => (bool) ($datos['es_retornable'] ?? false),
            'stock_actual' => max(0, (float) ($datos['stock_actual'] ?? 0)),
            'stock_minimo' => max(0, (float) ($datos['stock_minimo'] ?? 0)),
            'ubicacion_id' => $datos['ubicacion_id'],
        ]);
    }

    /**
     * Modifica la información y/o ubicación física detallada de un insumo (CP-11).
     *
     * @param array<string, mixed> $datos
     */
    public function actualizarInsumo(Insumo $insumo, array $datos): Insumo
    {
        if (isset($datos['ubicacion_id']) && ! Ubicacion::where('id', $datos['ubicacion_id'])->exists()) {
            throw ValidationException::withMessages([
                'ubicacion_id' => 'La ubicación especificada no existe.',
            ]);
        }

        if (isset($datos['stock_minimo']) && (float) $datos['stock_minimo'] < 0) {
            throw ValidationException::withMessages([
                'stock_minimo' => 'El stock mínimo no puede ser negativo.',
            ]);
        }

        $insumo->update($datos);

        return $insumo->fresh(['ubicacion']);
    }

    /**
     * Registra un ingreso de insumo al stock (por compra o ingreso manual) (CP-13).
     *
     * @param 'ingreso_compra'|'ingreso_manual'|'reingreso_devolucion' $tipo
     */
    public function registrarIngreso(
        Insumo $insumo,
        float $cantidad,
        User $usuario,
        string $tipo = 'ingreso_manual',
        ?int $compraId = null,
        ?int $solicitudId = null
    ): MovimientoInventario {
        $this->validarCantidadPositiva($cantidad);

        $tiposValidos = ['ingreso_compra', 'ingreso_manual', 'reingreso_devolucion'];
        if (! in_array($tipo, $tiposValidos, true)) {
            throw new InvalidArgumentException("Tipo de ingreso inválido: {$tipo}");
        }

        return DB::transaction(function () use ($insumo, $cantidad, $usuario, $tipo, $compraId, $solicitudId) {
            // Bloqueo pesimista para evitar condiciones de carrera (PI-02)
            /** @var Insumo $insumoBloqueado */
            $insumoBloqueado = Insumo::where('id', $insumo->id)->lockForUpdate()->firstOrFail();

            $insumoBloqueado->stock_actual = (float) $insumoBloqueado->stock_actual + $cantidad;
            $insumoBloqueado->save();

            $movimiento = MovimientoInventario::create([
                'insumo_id' => $insumoBloqueado->id,
                'usuario_id' => $usuario->id,
                'compra_id' => $compraId,
                'solicitud_id' => $solicitudId,
                'tipo' => $tipo,
                'cantidad' => $cantidad,
                'fecha' => now(),
            ]);

            return $movimiento->load('insumo', 'usuario');
        });
    }

    /**
     * Registra una salida de insumo del stock (consumo de taller, préstamo o merma) (CP-13).
     * Bloquea terminantemente cantidades negativas, cero o superiores al stock actual.
     *
     * @param 'salida_prestamo'|'salida_consumo' $tipo
     */
    public function registrarSalida(
        Insumo $insumo,
        float $cantidad,
        User $usuario,
        string $tipo = 'salida_consumo',
        ?int $solicitudId = null
    ): MovimientoInventario {
        $this->validarCantidadPositiva($cantidad);

        $tiposValidos = ['salida_prestamo', 'salida_consumo'];
        if (! in_array($tipo, $tiposValidos, true)) {
            throw new InvalidArgumentException("Tipo de salida inválido: {$tipo}");
        }

        return DB::transaction(function () use ($insumo, $cantidad, $usuario, $tipo, $solicitudId) {
            // Bloqueo pesimista sobre el insumo
            /** @var Insumo $insumoBloqueado */
            $insumoBloqueado = Insumo::where('id', $insumo->id)->lockForUpdate()->firstOrFail();

            $stockActual = (float) $insumoBloqueado->stock_actual;

            if ($cantidad > $stockActual) {
                throw new InvalidArgumentException(
                    "Stock insuficiente. Existencias actuales: {$stockActual}, cantidad solicitada: {$cantidad}."
                );
            }

            $insumoBloqueado->stock_actual = $stockActual - $cantidad;
            $insumoBloqueado->save();

            $movimiento = MovimientoInventario::create([
                'insumo_id' => $insumoBloqueado->id,
                'usuario_id' => $usuario->id,
                'compra_id' => null,
                'solicitud_id' => $solicitudId,
                'tipo' => $tipo,
                'cantidad' => $cantidad,
                'fecha' => now(),
            ]);

            return $movimiento->load('insumo', 'usuario');
        });
    }

    /**
     * Registra un ajuste de inventario tras conteo físico o auditoría.
     */
    public function registrarAjuste(
        Insumo $insumo,
        float $nuevoStock,
        User $usuario,
        string $justificacion
    ): MovimientoInventario {
        if ($nuevoStock < 0) {
            throw new InvalidArgumentException('El stock resultante de un ajuste no puede ser negativo.');
        }

        if (trim($justificacion) === '') {
            throw new InvalidArgumentException('Debe ingresar una justificación para el ajuste de inventario.');
        }

        return DB::transaction(function () use ($insumo, $nuevoStock, $usuario, $justificacion) {
            /** @var Insumo $insumoBloqueado */
            $insumoBloqueado = Insumo::where('id', $insumo->id)->lockForUpdate()->firstOrFail();

            $diferencia = $nuevoStock - (float) $insumoBloqueado->stock_actual;

            $insumoBloqueado->stock_actual = $nuevoStock;
            $insumoBloqueado->save();

            // En el kardex registramos el valor absoluto movido con tipo ajuste
            return MovimientoInventario::create([
                'insumo_id' => $insumoBloqueado->id,
                'usuario_id' => $usuario->id,
                'compra_id' => null,
                'solicitud_id' => null,
                'tipo' => 'ajuste',
                'cantidad' => abs($diferencia),
                'fecha' => now(),
            ]);
        });
    }

    /**
     * Determina de forma reactiva si un insumo se encuentra en nivel de stock crítico (KPI-04, CP-14).
     * Devuelve true cuando stock_actual <= stock_minimo.
     */
    public function esStockCritico(Insumo $insumo): bool
    {
        return (float) $insumo->stock_actual <= (float) $insumo->stock_minimo;
    }

    /**
     * Obtiene el listado de insumos en condición de stock crítico (KPI-04).
     */
    public function obtenerInsumosCriticos(?int $carreraId = null): Collection
    {
        $query = Insumo::with(['ubicacion.carrera'])
            ->whereRaw('stock_actual <= stock_minimo');

        if ($carreraId) {
            $query->whereHas('ubicacion', fn ($q) => $q->where('carrera_id', $carreraId));
        }

        return $query->get();
    }

    /**
     * Valida que la cantidad para un movimiento sea un número entero o decimal estrictamente positivo (> 0) (CP-13).
     */
    protected function validarCantidadPositiva(float $cantidad): void
    {
        if ($cantidad <= 0) {
            throw new InvalidArgumentException('La cantidad del movimiento debe ser un valor positivo mayor a cero (0).');
        }
    }

    /**
     * Valida los atributos de creación de un insumo.
     *
     * @param array<string, mixed> $datos
     */
    protected function validarDatosInsumo(array $datos): void
    {
        $errores = [];

        if (empty($datos['codigo_barras']) || trim($datos['codigo_barras']) === '') {
            $errores['codigo_barras'] = 'El código de barras es obligatorio.';
        } elseif (Insumo::where('codigo_barras', trim($datos['codigo_barras']))->exists()) {
            $errores['codigo_barras'] = 'El código de barras ya se encuentra registrado.';
        }

        if (empty($datos['nombre']) || trim($datos['nombre']) === '') {
            $errores['nombre'] = 'El nombre estandarizado del insumo es obligatorio.';
        }

        if (empty($datos['unidad']) || trim($datos['unidad']) === '') {
            $errores['unidad'] = 'La unidad de medida es obligatoria.';
        }

        if (empty($datos['ubicacion_id']) || ! Ubicacion::where('id', $datos['ubicacion_id'])->exists()) {
            $errores['ubicacion_id'] = 'La ubicación física detallada es obligatoria y debe existir.';
        }

        if (isset($datos['stock_minimo']) && (float) $datos['stock_minimo'] < 0) {
            $errores['stock_minimo'] = 'El stock mínimo no puede ser negativo.';
        }

        if (! empty($errores)) {
            throw ValidationException::withMessages($errores);
        }
    }
}
