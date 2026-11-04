<?php

namespace App\Jobs;

use App\Mail\ReporteIncidenciaMail;
use App\Models\Falla;
use App\Models\ParametroSistema;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class EnviarReporteIncidenciaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Falla $falla) {}

    public function handle(): void
    {
        // Verificar si la notificación por correo está habilitada en los parámetros globales
        $habilitada = ParametroSistema::get('notificar_incidencias_pdf', true);
        if (! $habilitada) {
            return;
        }

        // Obtener destinatarios activos con rol 'encargado_panol' o 'administrador'
        $destinatarios = User::whereIn('rol', ['encargado_panol', 'administrador'])
            ->where('activo', true)
            ->pluck('email')
            ->filter()
            ->all();

        if (empty($destinatarios)) {
            return;
        }

        Mail::to($destinatarios)->send(new ReporteIncidenciaMail($this->falla));
    }
}
