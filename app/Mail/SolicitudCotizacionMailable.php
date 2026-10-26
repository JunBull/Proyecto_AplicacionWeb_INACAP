<?php

namespace App\Mail;

use App\Models\Proveedor;
use App\Models\SolicitudCotizacion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SolicitudCotizacionMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SolicitudCotizacion $solicitud,
        public Proveedor $proveedor
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Solicitud de Cotización #{$this->solicitud->id} - INACAP Sede Temuco",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.solicitud_cotizacion',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
