<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A3 (v1.7.6) — the "Send test email" probe from Admin → Email.
 *
 * It carries the CURRENTLY CONFIGURED mailer name, host and port, because
 * the whole point is to prove which rail the mail actually took — a mail
 * that arrives with no clue about the host is not evidence of anything.
 *
 * (`$mailer` is named `$rail` on purpose: Mailable already owns a `$mailer`
 * property and redeclaring it is a fatal error.)
 */
class TestEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipient,
        public readonly string $rail,
        public readonly string $host,
        public readonly int $port,
        public readonly string $siteName,
    ) {}

    public function envelope(): Envelope
    {
        // The recipient lives on the Mailable, not in a send callback: a
        // callback never runs under Mail::fake(), which would leave the
        // probe unassertable (and, more importantly, hides who it went to).
        return new Envelope(to: $this->recipient, subject: $this->siteName.' — test email');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.admin.test',
            with: [
                'mailer' => $this->rail,
                'host' => $this->host,
                'port' => $this->port,
                'siteName' => $this->siteName,
            ],
            text: 'mail.admin.test-text',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}