<?php

namespace App\Jobs;

use App\Services\CotizacionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DespacharCotizacionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número máximo de reintentos del trabajo en cola.
     */
    public int $tries = 3;

    /**
     * Segundos de espera antes de reintentar ante fallo temporal.
     */
    public int $backoff = 10;

    public function __construct(
        public int $envioCotizacionId
    ) {}

    /**
     * Procesa el despacho asíncrono del correo de cotización al proveedor (SLA-05, KPI-10).
     */
    public function handle(CotizacionService $cotizacionService): void
    {
        $cotizacionService->procesarEnvioIndividual($this->envioCotizacionId);
    }
}
