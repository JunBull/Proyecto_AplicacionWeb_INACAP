<?php

namespace App\Mail;

use App\Models\Entrega;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class AlertaRetrasoEstudianteMail extends Mailable
{
    use Queueable, SerializesModels;

    public Carbon $fechaHoraAcordada;

    public Carbon $fechaActual;

    public string $retrasoHumano;

    public int $retrasoMinutos;

    public Collection $itemsPendientes;

    public function __construct(public Entrega $entrega)
    {
        $this->fechaHoraAcordada = $entrega->fecha_hora_acordada ?? now();
        $this->fechaActual = now();
        $this->retrasoMinutos = (int) max(0, $this->fechaActual->diffInMinutes($this->fechaHoraAcordada));
        $this->retrasoHumano = $this->fechaActual->diffForHumans($this->fechaHoraAcordada, [
            'syntax' => Carbon::DIFF_ABSOLUTE,
            'parts' => 2,
        ]);

        $this->itemsPendientes = $entrega->solicitud->detalles->filter(function ($det) {
            $pendiente = (float) $det->cantidad_entregada - (float) $det->cantidad_devuelta;

            return $pendiente > 0 && ($det->equipo_id !== null || ($det->insumo && $det->insumo->es_retornable));
        });
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '⚠️ Alerta de Retraso en Devolución: Estudiante '.$this->entrega->receptor_nombre.' (#ENT-'.$this->entrega->id.') - INACAP Temuco',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.alerta_retraso_estudiante',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
