<?php

namespace App\Services;

use App\Models\Carrera;
use App\Models\Ubicacion;
use InvalidArgumentException;

class UbicacionService
{
    /**
     * Niveles jerárquicos válidos según especificación técnica.
     *
     * @var array<int, string>
     */
    public const TIPOS_VALIDOS = ['panol', 'laboratorio', 'mueble', 'cajon', 'puerta', 'subdivision'];

    /**
     * Registra una nueva ubicación respetando la jerarquía institucional.
     *
     * @param array{
     *     carrera_id: int,
     *     padre_id?: int|null,
     *     tipo: string,
     *     nombre: string
     * } $datos
     */
    public function registrarUbicacion(array $datos): Ubicacion
    {
        $this->validarDatos($datos);

        return Ubicacion::create([
            'carrera_id' => $datos['carrera_id'],
            'padre_id' => $datos['padre_id'] ?? null,
            'tipo' => $datos['tipo'],
            'nombre' => trim($datos['nombre']),
        ]);
    }

    /**
     * Modifica los atributos de una ubicación existente.
     *
     * @param array{
     *     carrera_id?: int,
     *     padre_id?: int|null,
     *     tipo?: string,
     *     nombre?: string
     * } $datos
     */
    public function actualizarUbicacion(Ubicacion $ubicacion, array $datos): Ubicacion
    {
        if (isset($datos['padre_id']) && $datos['padre_id'] === $ubicacion->id) {
            throw new InvalidArgumentException('Una ubicación no puede ser padre de sí misma.');
        }

        if (isset($datos['tipo']) && ! in_array($datos['tipo'], self::TIPOS_VALIDOS, true)) {
            throw new InvalidArgumentException("Tipo de ubicación inválido: {$datos['tipo']}");
        }

        $ubicacion->update(array_filter([
            'carrera_id' => $datos['carrera_id'] ?? null,
            'padre_id' => array_key_exists('padre_id', $datos) ? $datos['padre_id'] : $ubicacion->padre_id,
            'tipo' => $datos['tipo'] ?? null,
            'nombre' => isset($datos['nombre']) ? trim($datos['nombre']) : null,
        ], fn ($val) => $val !== null || $val === null && array_key_exists('padre_id', $datos)));

        return $ubicacion->fresh(['padre', 'carrera']);
    }

    /**
     * Obtiene la ruta jerárquica completa textual de una ubicación (Breadcrumb).
     * Ej: "Ingeniería en Informática > Pañol Central > Laboratorio Redes > Mueble A > Cajón 01"
     */
    public function obtenerRutaCompleta(Ubicacion $ubicacion): string
    {
        $nodos = [];
        $actual = $ubicacion->relationLoaded('padre') ? $ubicacion : $ubicacion->load('padre', 'carrera');

        while ($actual) {
            array_unshift($nodos, "{$actual->nombre} ({$actual->tipo})");
            $actual = $actual->padre;
        }

        $nombreCarrera = $ubicacion->carrera?->nombre ?? 'Sin Carrera';

        return $nombreCarrera . ' > ' . implode(' > ', $nodos);
    }

    /**
     * Valida la coherencia de datos de la ubicación.
     *
     * @param array<string, mixed> $datos
     */
    protected function validarDatos(array $datos): void
    {
        if (empty($datos['nombre']) || trim($datos['nombre']) === '') {
            throw new InvalidArgumentException('El nombre de la ubicación es obligatorio.');
        }

        if (empty($datos['carrera_id']) || ! Carrera::where('id', $datos['carrera_id'])->exists()) {
            throw new InvalidArgumentException('La carrera asociada no existe o no fue especificada.');
        }

        if (empty($datos['tipo']) || ! in_array($datos['tipo'], self::TIPOS_VALIDOS, true)) {
            throw new InvalidArgumentException('El tipo de ubicación no es válido.');
        }

        if (! empty($datos['padre_id'])) {
            $padre = Ubicacion::find($datos['padre_id']);
            if (! $padre) {
                throw new InvalidArgumentException('La ubicación superior (padre) no existe.');
            }
            if ($padre->carrera_id !== (int) $datos['carrera_id']) {
                throw new InvalidArgumentException('La ubicación padre pertenece a una carrera distinta.');
            }
        }
    }
}
