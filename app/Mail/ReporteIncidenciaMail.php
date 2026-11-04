<?php

namespace App\Mail;

use App\Models\Falla;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ReporteIncidenciaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Falla $falla) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Alerta de Incidencia Técnica: Reporte #'.$this->falla->id.' - INACAP Temuco',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reporte_incidencia',
        );
    }

    public function attachments(): array
    {
        $documento = $this->falla->documentos()->first();
        if ($documento && Storage::disk('public')->exists($documento->ruta)) {
            return [
                Attachment::fromStorageDisk('public', $documento->ruta)
                    ->as($documento->nombre_original)
                    ->withMime('application/pdf'),
            ];
        }

        return [];
    }
}
