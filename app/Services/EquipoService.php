<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Documento;
use App\Models\Equipo;
use App\Models\Subcategoria;
use App\Models\Ubicacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
     *     categoria_id?: int|null,
     *     subcategoria_id?: int|null,
     *     etiqueta_impresa?: bool,
     *     compra_id?: int|null,
     *     fotografia?: string|UploadedFile|null,
     *     fecha_adquisicion?: string|null,
     *     estado?: string
     * } $datos
     *
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

            $subcategoriaId = isset($datos['subcategoria_id']) && $datos['subcategoria_id'] !== ''
                ? (int) $datos['subcategoria_id']
                : null;

            $categoriaId = $datos['categoria_id'] ?? null;
            if (! $categoriaId && $subcategoriaId) {
                $categoriaId = Subcategoria::where('id', $subcategoriaId)->value('categoria_id');
            }
            if (! $categoriaId && Categoria::exists()) {
                $categoriaId = Categoria::where('slug', 'computo-movil')->value('id') ?? Categoria::first()?->id;
            }

            $criticidad = $datos['criticidad'] ?? null;
            if (! $criticidad) {
                if ($categoriaId) {
                    $criticidad = Categoria::find($categoriaId)?->criticidad_sugerida ?? 'media';
                } else {
                    $criticidad = 'media';
                }
            }

            $equipo = Equipo::create([
                'codigo_barras' => trim($datos['codigo_barras']),
                'nombre' => trim($datos['nombre']),
                'marca' => trim($datos['marca']),
                'modelo' => trim($datos['modelo']),
                'numero_serie' => trim($datos['numero_serie']),
                'estado' => $datos['estado'] ?? 'disponible',
                'criticidad' => $criticidad,
                'ubicacion_id' => $datos['ubicacion_id'],
                'categoria_id' => $categoriaId,
                'subcategoria_id' => $subcategoriaId,
                'etiqueta_impresa' => (bool) ($datos['etiqueta_impresa'] ?? false),
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
                'hash_sha256' => hash('sha256', $rutaFoto.'|'.$equipo->id.'|'.microtime(true)),
                'documentable_type' => Equipo::class,
                'documentable_id' => $equipo->id,
                'subido_por' => $subidoPorId,
                'creado_en' => now(),
            ]);

            return $equipo->fresh(['ubicacion', 'categoria', 'subcategoria', 'documentos']);
        });
    }

    /**
     * Actualiza la ficha técnica de un equipo.
     *
     * @param  array<string, mixed>  $datos
     */
    public function actualizarEquipo(Equipo $equipo, array $datos, ?User $usuarioResponsable = null): Equipo
    {
        if (isset($datos['nombre']) && ! $this->esNombreEstandarizado($datos['nombre'])) {
            throw ValidationException::withMessages([
                'nombre' => 'El nombre del activo incumple la política de estandarización (se prohíben siglas ambiguas).',
            ]);
        }

        if (isset($datos['numero_serie'])) {
            $serieLimpia = trim((string) $datos['numero_serie']);
            if ($serieLimpia === '') {
                throw ValidationException::withMessages([
                    'numero_serie' => 'El número de serie de fábrica es obligatorio.',
                ]);
            }
            $existeSerie = Equipo::whereRaw('LOWER(numero_serie) = ?', [mb_strtolower($serieLimpia)])
                ->where('id', '!=', $equipo->id)
                ->exists();
            if ($existeSerie) {
                throw ValidationException::withMessages([
                    'numero_serie' => 'El número de serie ya se encuentra registrado en el sistema.',
                ]);
            }
        }

        if (isset($datos['ubicacion_id']) && ! Ubicacion::where('id', $datos['ubicacion_id'])->exists()) {
            throw ValidationException::withMessages([
                'ubicacion_id' => 'La ubicación especificada no es válida.',
            ]);
        }

        if (isset($datos['categoria_id']) && $datos['categoria_id'] !== null && ! Categoria::where('id', $datos['categoria_id'])->exists()) {
            throw ValidationException::withMessages([
                'categoria_id' => 'La categoría especificada no es válida.',
            ]);
        }

        if (isset($datos['subcategoria_id']) && $datos['subcategoria_id'] !== null) {
            $sub = Subcategoria::find($datos['subcategoria_id']);
            if (! $sub) {
                throw ValidationException::withMessages([
                    'subcategoria_id' => 'La subcategoría especificada no es válida.',
                ]);
            }
            $catObjetivo = $datos['categoria_id'] ?? $equipo->categoria_id;
            if ($catObjetivo && (int) $sub->categoria_id !== (int) $catObjetivo) {
                throw ValidationException::withMessages([
                    'subcategoria_id' => 'La subcategoría no pertenece a la categoría seleccionada.',
                ]);
            }
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
                    'hash_sha256' => hash('sha256', $rutaFoto.'|'.$equipo->id.'|'.microtime(true)),
                    'documentable_type' => Equipo::class,
                    'documentable_id' => $equipo->id,
                    'subido_por' => $subidoPorId,
                    'creado_en' => now(),
                ]);
            }

            $equipo->update($datos);

            return $equipo->fresh(['ubicacion', 'categoria', 'subcategoria', 'documentos']);
        });
    }

    /**
     * Búsqueda de equipos mediante nombre estandarizado, código de barras o número de serie (CP-04).
     */
    public function buscar(string $termino, ?int $carreraId = null, ?int $categoriaId = null, ?int $subcategoriaId = null): Collection
    {
        $query = Equipo::with(['ubicacion.carrera', 'categoria', 'subcategoria', 'documentos']);

        if (trim($termino) !== '') {
            $t = '%'.trim($termino).'%';
            $query->where(function ($q) use ($t) {
                $q->where('nombre', 'LIKE', $t)
                    ->orWhere('codigo_barras', 'LIKE', $t)
                    ->orWhere('marca', 'LIKE', $t)
                    ->orWhere('modelo', 'LIKE', $t)
                    ->orWhere('numero_serie', 'LIKE', $t)
                    ->orWhereHas('categoria', function ($cq) use ($t) {
                        $cq->where('nombre', 'LIKE', $t);
                    })
                    ->orWhereHas('subcategoria', function ($sq) use ($t) {
                        $sq->where('nombre', 'LIKE', $t);
                    });
            });
        }

        if ($carreraId) {
            $query->whereHas('ubicacion', function ($q) use ($carreraId) {
                $q->where('carrera_id', $carreraId);
            });
        }

        if ($categoriaId) {
            $query->where('categoria_id', $categoriaId);
        }

        if ($subcategoriaId) {
            $query->where('subcategoria_id', $subcategoriaId);
        }

        return $query->get();
    }

    /**
     * Sugiere el siguiente código de barras correlativo usando el formato unificado
     * [PREFIJO_SUBCATEGORIA]-[MARCA_3]-[MODELO_CORTO]-[NN].
     */
    public function sugerirCodigoBarrasPorSubcategoria(?int $subcategoriaId, ?string $marca = null, ?string $modelo = null): string
    {
        $prefijo = $subcategoriaId
            ? Subcategoria::where('id', $subcategoriaId)->value('codigo_prefijo')
            : null;

        if (! $prefijo) {
            return 'EQ-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        }

        $marcaCodigo = $this->abreviarMarca($marca);
        $modeloCodigo = $this->abreviarModelo($modelo);

        $base = ($marcaCodigo !== '' && $modeloCodigo !== '')
            ? "{$prefijo}-{$marcaCodigo}-{$modeloCodigo}"
            : $prefijo;

        return $this->siguienteCorrelativoPorBase($base);
    }

    /**
     * Obtiene las familias/modelos existentes dentro de una subcategoría para el modal Nuevo Equipo (Escenario A).
     *
     * @return \Illuminate\Support\Collection<int, array{
     *     codigo_base: string,
     *     nombre: string,
     *     marca: string,
     *     modelo: string,
     *     total_unidades: int,
     *     siguiente_codigo: string
     * }>
     */
    public function obtenerFamiliasPorSubcategoria(int $subcategoriaId): \Illuminate\Support\Collection
    {
        $equipos = Equipo::query()
            ->where('subcategoria_id', $subcategoriaId)
            ->whereNotNull('codigo_barras')
            ->where('codigo_barras', '!=', '')
            ->orderBy('codigo_barras')
            ->get();

        return $equipos
            ->groupBy(function (Equipo $eq): string {
                $base = preg_replace('/-\d+$/', '', trim((string) $eq->codigo_barras));

                return $base !== null && $base !== '' ? $base : trim((string) $eq->codigo_barras);
            })
            ->map(function (Collection $grupo, string $codigoBase): array {
                /** @var Equipo $primero */
                $primero = $grupo->first();

                return [
                    'codigo_base' => $codigoBase,
                    'nombre' => $primero->nombre,
                    'marca' => $primero->marca,
                    'modelo' => $primero->modelo,
                    'total_unidades' => $grupo->count(),
                    'siguiente_codigo' => $this->siguienteCorrelativoPorBase($codigoBase),
                ];
            })
            ->sortBy('codigo_base')
            ->values();
    }

    /**
     * Calcula el siguiente código correlativo disponible (-01, -02...) para una familia base.
     */
    public function siguienteCorrelativoPorBase(string $codigoBase): string
    {
        $baseLimpia = rtrim(strtoupper(trim($codigoBase)), '-');

        $codigosExistentes = Equipo::where('codigo_barras', 'LIKE', $baseLimpia.'-%')
            ->pluck('codigo_barras');

        $maxCorrelativo = 0;
        $patron = '/^'.preg_quote($baseLimpia, '/').'-(\d+)$/i';
        foreach ($codigosExistentes as $codigo) {
            if (preg_match($patron, (string) $codigo, $matches)) {
                $maxCorrelativo = max($maxCorrelativo, (int) $matches[1]);
            }
        }

        $siguiente = $maxCorrelativo + 1;
        $candidato = sprintf('%s-%02d', $baseLimpia, $siguiente);
        while (Equipo::where('codigo_barras', $candidato)->exists()) {
            $siguiente++;
            $candidato = sprintf('%s-%02d', $baseLimpia, $siguiente);
        }

        return $candidato;
    }

    /**
     * Construye el código completo para un modelo nuevo combinando el prefijo fijo de subcategoría y el sufijo ingresado (Escenario B).
     */
    public function construirCodigoDesdePrefijo(string $prefijoSubcategoria, string $sufijoManual): string
    {
        $prefijo = rtrim(strtoupper(trim($prefijoSubcategoria)), '-');
        $sufijo = strtoupper(trim(iconv('UTF-8', 'ASCII//TRANSLIT', $sufijoManual) ?: $sufijoManual));
        $sufijo = preg_replace('/[\s_]+/', '-', $sufijo) ?? '';
        $sufijo = preg_replace('/[^A-Z0-9\-]/', '', $sufijo) ?? '';
        $sufijo = preg_replace('/-+/', '-', $sufijo) ?? '';
        $sufijo = trim($sufijo, '-');

        if ($prefijo !== '' && str_starts_with($sufijo, $prefijo.'-')) {
            $sufijo = substr($sufijo, strlen($prefijo) + 1);
        }
        $sufijo = preg_replace('/-\d+$/', '', $sufijo) ?? $sufijo;
        $sufijo = trim($sufijo, '-');

        if ($sufijo === '') {
            return '';
        }

        $base = $prefijo !== '' ? "{$prefijo}-{$sufijo}" : $sufijo;

        return $this->siguienteCorrelativoPorBase($base);
    }

    protected function abreviarMarca(?string $marca): string
    {
        $limpia = strtolower(trim((string) $marca));
        if ($limpia === '') {
            return '';
        }

        $aliases = [
            'lenovo' => 'LNV',
            'apple' => 'APL',
            'dell' => 'DEL',
            'samsung' => 'SAM',
            'wacom' => 'WAC',
            'huion' => 'HUI',
            'cisco' => 'CSC',
            'aruba' => 'ARU',
            'mikrotik' => 'MIK',
            'ubiquiti' => 'UBI',
            'fortinet' => 'FTN',
            'grandstream' => 'GRD',
            'fluke' => 'FLK',
            'tektronix' => 'TEK',
            'rigol' => 'RIG',
            'uni-t' => 'UNI',
            'korad' => 'KOR',
            'siglent' => 'SIG',
            'arduino' => 'ARD',
            'espressif' => 'ESP',
            'raspberry' => 'RPI',
            'nvidia' => 'NVD',
            'intel' => 'INT',
            'hiwonder' => 'HIW',
            'makeblock' => 'MOD',
            'zebra' => 'ZEB',
            'brother' => 'BRO',
            'creality' => 'CRE',
            'bambu' => 'BMB',
            'honeywell' => 'HON',
            'epson' => 'EPS',
            'viewsonic' => 'VWS',
            'logitech' => 'LOG',
            'meta' => 'MET',
            'htc' => 'HTC',
            'edifier' => 'EDF',
            'rode' => 'ROD',
            'sumitomo' => 'SUM',
            'tp-link' => 'TPL',
            'towerpro' => 'TOW',
            'dji' => 'DJI',
            'genérico' => 'GEN',
            'generico' => 'GEN',
        ];

        foreach ($aliases as $clave => $codigo) {
            if (str_contains($limpia, $clave)) {
                return $codigo;
            }
        }

        $soloLetras = preg_replace('/[^A-Z0-9]/', '', strtoupper(iconv('UTF-8', 'ASCII//TRANSLIT', $limpia) ?: $limpia));

        return substr((string) $soloLetras, 0, 3);
    }

    protected function abreviarModelo(?string $modelo): string
    {
        $limpia = trim((string) $modelo);
        if ($limpia === '') {
            return '';
        }

        $tokens = preg_split('/[\s\/\-_]+/', strtoupper(iconv('UTF-8', 'ASCII//TRANSLIT', $limpia) ?: $limpia)) ?: [];
        $ignorados = ['GEN', 'PRO', 'HD', 'WIFI', 'GB', 'RAM', 'PLUS', 'COMBO', 'SE', 'V2', 'V3', 'REV3', 'REV', '2D', '1D', 'QR'];

        foreach ($tokens as $token) {
            $t = preg_replace('/[^A-Z0-9]/', '', $token) ?? '';
            if ($t === '' || in_array($t, $ignorados, true)) {
                continue;
            }
            if (preg_match('/\d/', $t) && strlen($t) >= 2 && ! preg_match('/^\d+(TH|ST|ND|RD|MA|VA)$/', $t)) {
                return substr($t, 0, 4);
            }
        }

        foreach ($tokens as $token) {
            $t = preg_replace('/[^A-Z0-9]/', '', $token) ?? '';
            if ($t !== '' && ! in_array($t, $ignorados, true)) {
                return substr($t, 0, 4);
            }
        }

        return '';
    }

    /**
     * Valida rigurosamente la presencia de campos obligatorios y formato estandarizado (CP-03, CP-04).
     *
     * @param  array<string, mixed>  $datos
     *
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

        if (empty($datos['numero_serie']) || trim((string) $datos['numero_serie']) === '') {
            $errores['numero_serie'] = 'El número de serie de fábrica es obligatorio.';
        } elseif (Equipo::whereRaw('LOWER(numero_serie) = ?', [mb_strtolower(trim((string) $datos['numero_serie']))])->exists()) {
            $errores['numero_serie'] = 'El número de serie ya se encuentra registrado en el sistema.';
        }

        if (empty($datos['ubicacion_id']) || ! Ubicacion::where('id', $datos['ubicacion_id'])->exists()) {
            $errores['ubicacion_id'] = 'La ubicación o laboratorio asignado es obligatorio y debe existir.';
        }

        if (isset($datos['categoria_id']) && $datos['categoria_id'] !== null && ! Categoria::where('id', $datos['categoria_id'])->exists()) {
            $errores['categoria_id'] = 'La categoría seleccionada no es válida.';
        }

        if (isset($datos['subcategoria_id']) && $datos['subcategoria_id'] !== null && $datos['subcategoria_id'] !== '') {
            $subcategoria = Subcategoria::find($datos['subcategoria_id']);
            if (! $subcategoria) {
                $errores['subcategoria_id'] = 'La subcategoría seleccionada no es válida.';
            } elseif (! empty($datos['categoria_id']) && (int) $subcategoria->categoria_id !== (int) $datos['categoria_id']) {
                $errores['subcategoria_id'] = 'La subcategoría seleccionada no pertenece a la categoría indicada.';
            }
        }

        if (isset($datos['fotografia']) && $datos['fotografia'] instanceof UploadedFile) {
            $ext = strtolower($datos['fotografia']->getClientOriginalExtension() ?: $datos['fotografia']->extension() ?: '');
            if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $errores['fotografia'] = 'La fotografía del equipo debe ser un archivo de imagen válido (jpg, jpeg, png, webp).';
            } elseif ($datos['fotografia']->getSize() > 5 * 1024 * 1024) {
                $errores['fotografia'] = 'La fotografía del equipo no debe superar los 5 MB.';
            }
        }

        if (! empty($errores)) {
            throw ValidationException::withMessages($errores);
        }
    }
}
