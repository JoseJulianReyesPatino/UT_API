<?php

namespace App\Mail;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DocumentReturned extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Document $document,
        public readonly string $comment,
        public readonly string $adminName,
        public readonly ?string $submittedAt = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu documento ha sido devuelto - UTSLRC'
        );
    }

    public function content(): Content
    {
        $submittedAt = $this->submittedAt
            ?: ($this->document->submitted_at
                ? $this->document->submitted_at->setTimezone('America/Hermosillo')->format('d/m/Y H:i')
                : 'No disponible');

        return new Content(
            view: 'emails.document-returned',
            with: [
                'documentTitle' => $this->document->title ?? 'Documento sin titulo',
                'documentType' => $this->document->apartado_label ?? 'Documento',
                'comment' => $this->comment,
                'adminName' => $this->adminName,
                'documentId' => $this->document->id,
                'submittedAt' => $submittedAt,
                'appUrl' => config('app.url'),
                'docenteName' => $this->document->uploader?->full_name ?? 'Docente',
                'docenteEmail' => $this->document->uploader?->email ?? '',
                'logoUrl' => 'https://tutorias.utslrc.edu.mx/images/LogotipoUTSLRC.webp',
            ]
        );
    }
}
