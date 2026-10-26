<?php

namespace App\Services;

use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class ProveedorService
{
    /**
     * Registra un nuevo proveedor en el directorio categorizado (CP-16).
     *
     * @param array{
     *     razon_social: string,
     *     rut: string,
     *     correo: string,
     *     telefono?: ?string,
     *     tipo: 'insumos'|'mantenimiento'|'ambos',
     *     activo?: bool
     * } $datos
     */
    public function registrarProveedor(array $datos): Proveedor
    {
        $this->validarDatosProveedor($datos);

        return Proveedor::create([
            'razon_social' => trim($datos['razon_social']),
            'rut' => trim($datos['rut']),
            'correo' => strtolower(trim($datos['correo'])),
            'telefono' => isset($datos['telefono']) ? trim($datos['telefono']) : null,
            'tipo' => $datos['tipo'],
            'activo' => (bool) ($datos['activo'] ?? true),
        ]);
    }

    /**
     * Actualiza la información de un proveedor existente.
     *
     * @param  array<string, mixed>  $datos
     */
    public function actualizarProveedor(Proveedor $proveedor, array $datos): Proveedor
    {
        $this->validarDatosProveedor($datos, $proveedor->id);

        $proveedor->update([
            'razon_social' => isset($datos['razon_social']) ? trim($datos['razon_social']) : $proveedor->razon_social,
            'rut' => isset($datos['rut']) ? trim($datos['rut']) : $proveedor->rut,
            'correo' => isset($datos['correo']) ? strtolower(trim($datos['correo'])) : $proveedor->correo,
            'telefono' => array_key_exists('telefono', $datos) ? ($datos['telefono'] ? trim($datos['telefono']) : null) : $proveedor->telefono,
            'tipo' => $datos['tipo'] ?? $proveedor->tipo,
            'activo' => isset($datos['activo']) ? (bool) $datos['activo'] : $proveedor->activo,
        ]);

        return $proveedor->fresh();
    }

    /**
     * Lista proveedores con filtros opcionales de rubro comercial y estado activo (CP-16).
     *
     * @param  'insumos'|'mantenimiento'|'ambos'|null  $tipo
     */
    public function listarProveedores(?string $tipo = null, ?bool $soloActivos = true): Collection
    {
        $query = Proveedor::query();

        if ($tipo !== null && $tipo !== '') {
            if ($tipo === 'insumos') {
                $query->whereIn('tipo', ['insumos', 'ambos']);
            } elseif ($tipo === 'mantenimiento') {
                $query->whereIn('tipo', ['mantenimiento', 'ambos']);
            } else {
                $query->where('tipo', $tipo);
            }
        }

        if ($soloActivos) {
            $query->where('activo', true);
        }

        return $query->orderBy('razon_social')->get();
    }

    /**
     * Cambia el estado activo/inactivo de un proveedor.
     */
    public function toggleActivo(Proveedor $proveedor): Proveedor
    {
        $proveedor->activo = ! $proveedor->activo;
        $proveedor->save();

        return $proveedor;
    }

    /**
     * Valida los datos requeridos para registrar o actualizar un proveedor.
     *
     * @param  array<string, mixed>  $datos
     */
    protected function validarDatosProveedor(array $datos, ?int $excluirId = null): void
    {
        $errores = [];

        if (empty($datos['razon_social']) || trim($datos['razon_social']) === '') {
            $errores['razon_social'] = 'La razón social del proveedor es obligatoria.';
        }

        if (empty($datos['rut']) || trim($datos['rut']) === '') {
            $errores['rut'] = 'El RUT del proveedor es obligatorio.';
        } else {
            $rut = trim($datos['rut']);
            $existeRut = Proveedor::where('rut', $rut)
                ->when($excluirId, fn ($q) => $q->where('id', '!=', $excluirId))
                ->exists();

            if ($existeRut) {
                $errores['rut'] = 'El RUT ya se encuentra registrado para otro proveedor.';
            }
        }

        if (empty($datos['correo']) || trim($datos['correo']) === '') {
            $errores['correo'] = 'El correo electrónico es obligatorio para el despacho de cotizaciones.';
        } elseif (! filter_var($datos['correo'], FILTER_VALIDATE_EMAIL)) {
            $errores['correo'] = 'El formato del correo electrónico no es válido.';
        }

        $tiposValidos = ['insumos', 'mantenimiento', 'ambos'];
        if (empty($datos['tipo']) || ! in_array($datos['tipo'], $tiposValidos, true)) {
            $errores['tipo'] = 'La clasificación del proveedor debe ser "insumos", "mantenimiento" o "ambos".';
        }

        if (! empty($errores)) {
            throw ValidationException::withMessages($errores);
        }
    }
}
