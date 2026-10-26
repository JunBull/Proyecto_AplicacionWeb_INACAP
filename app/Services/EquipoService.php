<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\Equipo;
use App\Models\Ubicacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class EquipoService
{
    /**
     * Patrones de siglas o códigos informales ambiguos prohibidos por política institucional (CP-04).
     */
    protected const PATRONES_AMBIGUOS_PROHIBIDOS = [
        '/^PC[-\s_]?\d+$/i',
        '/^SW[-\s_]?\d+$/i',
        '/^RTR[-\s_]?\d+$/i',
        '/^SRV[-\s_]?\d+$/i',
        '/^EQ[-\s_]?\d+$/i',
        '/^[A-Z]{1,4}[-_\s]?\d+$/i',
    ];

    /**
     * Valida si un nombre cumple con la convención de nombres estandarizada (CP-04).
     */
    public function esNombreEstandarizado(string $nombre): bool
    {
        $nombreLimpio = trim($nombre);

        if (mb_strlen($nombreLimpio) < 10) {
            return false;
        }

        foreach (self::PATRONES_AMBIGUOS_PROHIBIDOS as $patron) {
            if (preg_match($patron, $nombreLimpio)) {
                return false;
            }
        }

        // Requiere al menos dos palabras descriptivas
        $palabras = preg_split('/\s+/', $nombreLimpio);
        if (count($palabras) < 2) {
            return false;
        }

        return true;
    }

    /**
     * Registra un equipo con ficha técnica completa y fotografía digital (CP-03, CP-04).
     *
     * @param array{
     *     codigo_barras: string,
     *     nombre: string,
     *     marca: string,
     *     modelo: string,
     *     numero_serie: string,
     *     criticidad?: 'alta'|'media'|'baja',
     *     ubicacion_id: int,
     *     compra_id?: int|null,
     *     fotografia?: string|UploadedFile|null,
     *     fecha_adquisicion?: string|null,
     *     estado?: string
     * } $datos
     * @param User|null $usuarioResponsable
     * @throws ValidationException
     */
    public function registrarEquipo(array $datos, ?User $usuarioResponsable = null): Equipo
    {
        $this->validarFichaTecnica($datos);

        return DB::transaction(function () use ($datos, $usuarioResponsable) {
            $rutaFoto = 'equipos/default.png';
            $archivoFoto = $datos['fotografia'] ?? null;

            if ($archivoFoto instanceof UploadedFile) {
                $rutaFoto = $archivoFoto->store('equipos/fotografias', 'public');
            } elseif (is_string($archivoFoto) && trim($archivoFoto) !== '') {
                $rutaFoto = trim($archivoFoto);
            }

            $equipo = Equipo::create([
                'codigo_barras' => trim($datos['codigo_barras']),
                'nombre' => trim($datos['nombre']),
                'marca' => trim($datos['marca']),
                'modelo' => trim($datos['modelo']),
                'numero_serie' => trim($datos['numero_serie']),
                'estado' => $datos['estado'] ?? 'disponible',
                'criticidad' => $datos['criticidad'] ?? 'media',
                'ubicacion_id' => $datos['ubicacion_id'],
                'compra_id' => $datos['compra_id'] ?? null,
                'fotografia' => $rutaFoto,
                'fecha_adquisicion' => $datos['fecha_adquisicion'] ?? now()->toDateString(),
            ]);

            // Asocia la fotografía en la tabla polimórfica 'documentos'
            $subidoPorId = $usuarioResponsable?->id ?? User::where('rol', 'encargado_panol')->value('id') ?? User::first()?->id ?? 1;

            Documento::create([
                'tipo' => 'otro',
                'ruta' => $rutaFoto,
                'nombre_original' => $archivoFoto instanceof UploadedFile ? $archivoFoto->getClientOriginalName() : basename($rutaFoto),
                'hash_sha256' => hash('sha256', $rutaFoto . '|' . $equipo->id . '|' . microtime(true)),
                'documentable_type' => Equipo::class,
                'documentable_id' => $equipo->id,
                'subido_por' => $subidoPorId,
                'creado_en' => now(),
            ]);

            return $equipo->fresh(['ubicacion', 'documentos']);
        });
    }

    /**
     * Actualiza la ficha técnica de un equipo.
     *
     * @param array<string, mixed> $datos
     */
    public function actualizarEquipo(Equipo $equipo, array $datos, ?User $usuarioResponsable = null): Equipo
    {
        if (isset($datos['nombre']) && ! $this->esNombreEstandarizado($datos['nombre'])) {
            throw ValidationException::withMessages([
                'nombre' => 'El nombre del activo incumple la política de estandarización (se prohíben siglas ambiguas).',
            ]);
        }

        if (isset($datos['ubicacion_id']) && ! Ubicacion::where('id', $datos['ubicacion_id'])->exists()) {
            throw ValidationException::withMessages([
                'ubicacion_id' => 'La ubicación especificada no es válida.',
            ]);
        }

        return DB::transaction(function () use ($equipo, $datos, $usuarioResponsable) {
            $archivoFoto = $datos['fotografia'] ?? null;
            if ($archivoFoto instanceof UploadedFile) {
                $rutaFoto = $archivoFoto->store('equipos/fotografias', 'public');
                $datos['fotografia'] = $rutaFoto;

                $subidoPorId = $usuarioResponsable?->id ?? User::first()?->id ?? 1;
                Documento::create([
                    'tipo' => 'otro',
                    'ruta' => $rutaFoto,
                    'nombre_original' => $archivoFoto->getClientOriginalName(),
                    'hash_sha256' => hash('sha256', $rutaFoto . '|' . $equipo->id . '|' . microtime(true)),
                    'documentable_type' => Equipo::class,
                    'documentable_id' => $equipo->id,
                    'subido_por' => $subidoPorId,
                    'creado_en' => now(),
                ]);
            }

            $equipo->update($datos);

            return $equipo->fresh(['ubicacion', 'documentos']);
        });
    }

    /**
     * Búsqueda de equipos mediante nombre estandarizado, código de barras o número de serie (CP-04).
     */
    public function buscar(string $termino, ?int $carreraId = null): Collection
    {
        $query = Equipo::with(['ubicacion.carrera', 'documentos']);

        if (trim($termino) !== '') {
            $t = '%' . trim($termino) . '%';
            $query->where(function ($q) use ($t) {
                $q->where('nombre', 'LIKE', $t)
                  ->orWhere('codigo_barras', 'LIKE', $t)
                  ->orWhere('marca', 'LIKE', $t)
                  ->orWhere('modelo', 'LIKE', $t)
                  ->orWhere('numero_serie', 'LIKE', $t);
            });
        }

        if ($carreraId) {
            $query->whereHas('ubicacion', function ($q) use ($carreraId) {
                $q->where('carrera_id', $carreraId);
            });
        }

        return $query->get();
    }

    /**
     * Valida rigurosamente la presencia de campos obligatorios y formato estandarizado (CP-03, CP-04).
     *
     * @param array<string, mixed> $datos
     * @throws ValidationException
     */
    protected function validarFichaTecnica(array $datos): void
    {
        $errores = [];

        if (empty($datos['codigo_barras']) || trim($datos['codigo_barras']) === '') {
            $errores['codigo_barras'] = 'El código de barras es un campo obligatorio.';
        } elseif (Equipo::where('codigo_barras', trim($datos['codigo_barras']))->exists()) {
            $errores['codigo_barras'] = 'El código de barras ya se encuentra registrado.';
        }

        if (empty($datos['nombre']) || trim($datos['nombre']) === '') {
            $errores['nombre'] = 'El nombre estandarizado del activo es obligatorio.';
        } elseif (! $this->esNombreEstandarizado($datos['nombre'])) {
            $errores['nombre'] = 'El nombre no cumple con la política estandarizada institucional (se prohíben siglas ambiguas como PC-01 o SW-L2).';
        }

        if (empty($datos['marca']) || trim($datos['marca']) === '') {
            $errores['marca'] = 'La marca del fabricante es obligatoria.';
        }

        if (empty($datos['modelo']) || trim($datos['modelo']) === '') {
            $errores['modelo'] = 'El modelo del equipo es obligatorio.';
        }

        if (empty($datos['numero_serie']) || trim($datos['numero_serie']) === '') {
            $errores['numero_serie'] = 'El número de serie de fábrica es obligatorio.';
        }

        if (empty($datos['ubicacion_id']) || ! Ubicacion::where('id', $datos['ubicacion_id'])->exists()) {
            $errores['ubicacion_id'] = 'La ubicación o laboratorio asignado es obligatorio y debe existir.';
        }

        if (! empty($errores)) {
            throw ValidationException::withMessages($errores);
        }
    }
}
