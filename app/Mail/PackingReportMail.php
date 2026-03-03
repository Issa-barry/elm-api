<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PackingReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly string $pdfContent,
        private readonly array  $filters,
        private readonly array  $summary,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Rapport Packings — ' . now()->format('d/m/Y'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.packings.report',
            with: [
                'filters' => $this->filters,
                'summary' => $this->summary,
            ],
        );
    }

    public function attachments(): array
    {
        // PDF en mémoire — pas de fichier temporaire sur le disque
        return [
            Attachment::fromData(
                fn () => $this->pdfContent,
                'rapport-packings-' . now()->format('Y-m-d') . '.pdf'
            )->withMime('application/pdf'),
        ];
    }
}
