<?php

namespace App\Services;

use App\Exceptions\SaldoInsuficienteException;
use App\Jobs\DespacharCotizacionJob;
use App\Mail\SolicitudCotizacionMailable;
use App\Models\Adjudicacion;
use App\Models\Carrera;
use App\Models\Cotizacion;
use App\Models\Documento;
use App\Models\EnvioCotizacion;
use App\Models\Proveedor;
use App\Models\SolicitudCotizacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

class CotizacionService
{
    public function __construct(
        protected PresupuestoService $presupuestoService
    ) {}

    /**
     * Registra una nueva solicitud de cotización en estado borrador (CP-17).
     *
     * @param array{
     *     carrera_id: int,
     *     descripcion: string
     * } $datos
     */
    public function crearSolicitudCotizacion(array $datos, User $usuario): SolicitudCotizacion
    {
        if (empty($datos['descripcion']) || trim($datos['descripcion']) === '') {
            throw ValidationException::withMessages([
                'descripcion' => 'El detalle y requerimientos de la cotización son obligatorios.',
            ]);
        }

        if (empty($datos['carrera_id']) || ! Carrera::where('id', $datos['carrera_id'])->exists()) {
            throw ValidationException::withMessages([
                'carrera_id' => 'La carrera asociada es obligatoria y debe existir en el sistema.',
            ]);
        }

        return SolicitudCotizacion::create([
            'carrera_id' => $datos['carrera_id'],
            'creada_por' => $usuario->id,
            'descripcion' => trim($datos['descripcion']),
            'estado' => 'borrador',
            'fecha_creacion' => now(),
        ]);
    }

    /**
     * Encola masivamente el despacho de correos a la lista de proveedores seleccionados (CP-17, SLA-05, KPI-10).
     *
     * @param  int[]  $proveedoresIds
     * @return EnvioCotizacion[]
     */
    public function despacharSolicitudProveedores(SolicitudCotizacion $solicitud, array $proveedoresIds): array
    {
        if (empty($proveedoresIds)) {
            throw ValidationException::withMessages([
                'proveedores' => 'Debe seleccionar al menos un proveedor para emitir la solicitud de cotización.',
            ]);
        }

        $proveedores = Proveedor::whereIn('id', $proveedoresIds)->where('activo', true)->get();
        if ($proveedores->isEmpty()) {
            throw ValidationException::withMessages([
                'proveedores' => 'Ninguno de los proveedores seleccionados se encuentra activo.',
            ]);
        }

        $enviosCreados = [];

        DB::transaction(function () use ($solicitud, $proveedores, &$enviosCreados) {
            $solicitud->update(['estado' => 'enviada']);

            foreach ($proveedores as $proveedor) {
                /** @var EnvioCotizacion $envio */
                $envio = EnvioCotizacion::create([
                    'solicitud_cotizacion_id' => $solicitud->id,
                    'proveedor_id' => $proveedor->id,
                    'estado' => 'pendiente',
                    'intentos' => 0,
                    'enviado_en' => null,
                    'error' => null,
                ]);

                DespacharCotizacionJob::dispatch($envio->id);
                $enviosCreados[] = $envio;
            }
        });

        return $enviosCreados;
    }

    /**
     * Ejecuta el intento de envío individual del correo y registra la bitácora de entrega (SLA-05, PI-05).
     */
    public function procesarEnvioIndividual(int $envioCotizacionId): EnvioCotizacion
    {
        /** @var EnvioCotizacion $envio */
        $envio = EnvioCotizacion::with(['solicitudCotizacion.carrera', 'proveedor'])->findOrFail($envioCotizacionId);

        $envio->intentos++;

        try {
            Mail::to($envio->proveedor->correo)->send(
                new SolicitudCotizacionMailable($envio->solicitudCotizacion, $envio->proveedor)
            );

            $envio->estado = 'enviado';
            $envio->enviado_en = now();
            $envio->error = null;
            $envio->save();
        } catch (Throwable $e) {
            // Manejo controlado de fallas SMTP sin caída de la aplicación (PI-05, SLA-05)
            $envio->estado = 'fallido';
            $envio->error = substr($e->getMessage(), 0, 1000);
            $envio->save();
        }

        return $envio;
    }

    /**
     * Carga formalmente una propuesta de cotización recibida de un proveedor (CP-18).
     */
    public function registrarCotizacion(
        SolicitudCotizacion $solicitud,
        Proveedor $proveedor,
        float $montoTotal,
        string $fechaRecepcion,
        ?UploadedFile $archivo = null,
        ?User $subidoPor = null
    ): Cotizacion {
        if ($montoTotal <= 0) {
            throw new InvalidArgumentException('El monto total ofertado en la cotización debe ser superior a cero.');
        }

        return DB::transaction(function () use ($solicitud, $proveedor, $montoTotal, $fechaRecepcion, $archivo, $subidoPor) {
            $cotizacion = Cotizacion::create([
                'solicitud_cotizacion_id' => $solicitud->id,
                'proveedor_id' => $proveedor->id,
                'monto_total' => $montoTotal,
                'fecha_recepcion' => $fechaRecepcion,
            ]);

            // Si la solicitud estaba en estado enviada, pasa a en_evaluacion
            if ($solicitud->estado === 'enviada') {
                $solicitud->update(['estado' => 'en_evaluacion']);
            }

            if ($archivo) {
                $hash = hash_file('sha256', $archivo->getRealPath());
                $ruta = $archivo->store('cotizaciones', 'local');

                Documento::create([
                    'tipo' => 'cotizacion',
                    'ruta' => $ruta,
                    'nombre_original' => $archivo->getClientOriginalName(),
                    'hash_sha256' => $hash,
                    'documentable_type' => Cotizacion::class,
                    'documentable_id' => $cotizacion->id,
                    'subido_por' => $subidoPor?->id ?? $solicitud->creada_por,
                    'creado_en' => now(),
                ]);
            }

            return $cotizacion->load('documentos', 'proveedor');
        });
    }

    /**
     * Adjudica formalmente una propuesta cotizada cumpliendo la regla de cuota mínima o excepción documentada (KPI-08, CP-18).
     */
    public function adjudicarCotizacion(
        Cotizacion $cotizacion,
        User $administrador,
        bool $excepcionAutorizada = false,
        ?string $justificacion = null
    ): Adjudicacion {
        // Validación 1: Una cotización solo puede adjudicarse una vez
        if (Adjudicacion::where('cotizacion_id', $cotizacion->id)->exists()) {
            throw ValidationException::withMessages([
                'cotizacion' => 'Esta propuesta de cotización ya fue adjudicada previamente.',
            ]);
        }

        $solicitud = $cotizacion->solicitudCotizacion;
        $totalPropuestas = $solicitud->cotizaciones()->count();

        // Validación 2: Regla de Negocio KPI-08 / CP-18 (Mínimo 2 a 3 cotizaciones o excepción justificada)
        if ($totalPropuestas < 2) {
            if (! $excepcionAutorizada) {
                throw ValidationException::withMessages([
                    'cotizacion' => "Se requiere un mínimo de 2 a 3 cotizaciones para adjudicar la compra. Se registran únicamente {$totalPropuestas} propuesta(s). Requiere autorización formal de excepción documentada.",
                ]);
            }

            if (empty($justificacion) || trim($justificacion) === '') {
                throw ValidationException::withMessages([
                    'justificacion' => 'La justificación es estrictamente obligatoria cuando se autoriza una adjudicación por excepción con menos de 2 cotizaciones (KPI-08).',
                ]);
            }
        }

        // Validación 3: Saldo presupuestario disponible de la carrera
        $montoOfertado = (float) $cotizacion->monto_total;
        if (! $this->presupuestoService->verificarDisponibilidad($solicitud->carrera, $montoOfertado)) {
            $saldoActual = $this->presupuestoService->consultarSaldo($solicitud->carrera);
            throw new SaldoInsuficienteException(
                "La carrera {$solicitud->carrera->nombre} no dispone de saldo presupuestario suficiente para adjudicar esta propuesta. Saldo actual: \${$saldoActual}, Monto cotizado: \${$montoOfertado}."
            );
        }

        return DB::transaction(function () use ($cotizacion, $solicitud, $administrador, $excepcionAutorizada, $justificacion) {
            $adjudicacion = Adjudicacion::create([
                'cotizacion_id' => $cotizacion->id,
                'adjudicada_por' => $administrador->id,
                'excepcion_autorizada' => $excepcionAutorizada,
                'justificacion' => $excepcionAutorizada ? trim($justificacion) : null,
            ]);

            $solicitud->update(['estado' => 'adjudicada']);

            return $adjudicacion->load('cotizacion.proveedor', 'adjudicador');
        });
    }

    /**
     * Lista solicitudes de cotización para una carrera o general.
     */
    public function listarSolicitudes(?int $carreraId = null): Collection
    {
        $query = SolicitudCotizacion::with(['carrera', 'creador', 'cotizaciones.proveedor', 'envios.proveedor'])
            ->latest('fecha_creacion');

        if ($carreraId) {
            $query->where('carrera_id', $carreraId);
        }

        return $query->get();
    }
}
