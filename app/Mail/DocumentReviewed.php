<?php

namespace App\Mail;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DocumentReviewed extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Document $document,
        public readonly string $adminName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu documento ha sido revisado - UTSLRC'
        );
    }

    public function content(): Content
    {
        // ✅ Usar reviewed_at (cuando el admin revisó) en lugar de submitted_at
        $reviewedAt = $this->document->reviewed_at
            ? $this->document->reviewed_at->setTimezone('America/Hermosillo')->format('d/m/Y H:i')
            : 'No disponible';

        return new Content(
            view: 'emails.document-reviewed',
            with: [
                'documentTitle' => $this->document->title ?? 'Documento sin titulo',
                'documentType' => $this->document->apartado_label ?? 'Documento',
                'adminName' => $this->adminName,
                'docenteName' => $this->document->uploader?->full_name ?? 'Docente',
                'appUrl' => config('app.url'),
                'reviewedAt' => $reviewedAt,
                'logoUrl' => 'https://tutorias.utslrc.edu.mx/images/LogotipoUTSLRC.webp',
            ]
        );
    }
}
