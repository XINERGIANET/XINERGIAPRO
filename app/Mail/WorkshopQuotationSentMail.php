<?php

namespace App\Mail;

use App\Models\WorkshopMovement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkshopQuotationSentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public WorkshopMovement $order,
        public string $attachmentPath,
        public string $attachmentName = 'cotizacion.xlsx'
    ) {
    }

    public function envelope(): Envelope
    {
        $subject = 'Cotizacion ' . ($this->order->quotation_correlative ?: $this->order->movement?->number ?: $this->order->id);

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.workshop_quotation_sent',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (!is_readable($this->attachmentPath)) {
            return [];
        }

        return [
            Attachment::fromPath($this->attachmentPath)
                ->as($this->attachmentName)
                ->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ];
    }
}
