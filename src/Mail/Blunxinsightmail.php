<?php

namespace Blunx\AI\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Blunx\AI\Models\BlunxInsight;
use Blunx\AI\Models\BlunxDashboardWidget;
use Blunx\AI\Models\BlunxDashboard;
use Illuminate\Support\Carbon;

use function Illuminate\Support\now;

/**
 * Mailable for an insight notification, with the PDF report attached.
 */
class BlunxInsightMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly BlunxInsight         $insight,
        public readonly BlunxDashboardWidget $widget,
        public readonly BlunxDashboard       $dashboard,
        public readonly string               $pdfContent = '',
        public readonly Carbon               $generatedAt,
    ) {}

    public function envelope(): Envelope
    {
        $emoji = match ($this->insight->severity) {
            'critical' => '🔴',
            'warning'  => '🟡',
            default    => '🔵',
        };

        return new Envelope(
            subject: "{$emoji} Blunx AI — Rapport : {$this->widget->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'blunx::mail.insight_mail',
        );
    }

    public function attachments(): array
    {
        if (empty($this->pdfContent)) return [];

        $filename = 'rapport-blunx-' . $this->generatedAt->format('Y-m-d') . '-' . str_replace([' ', '/'], '-', $this->widget->title) . '.pdf';

        return [
            Attachment::fromData(fn () => $this->pdfContent, $filename)
                ->withMime('application/pdf'),
        ];
    }
}