<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A1 (v1.7.6) — the password-reset message.
 *
 * A Mailable (not a raw `MailMessage`) on purpose: the notification channel
 * hands a returned Mailable straight to the mailer, so `Mail::fake()` sees
 * it and the test can extract the reset token from the REAL rendered mail
 * instead of trusting that some view "should" contain it.
 *
 * The body never contains the account's password — only the broker token.
 */
class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipient,
        public readonly string $url,
        public readonly int $minutes,
        public readonly string $siteName,
        public readonly string $name,
        /** S9 (v1.8.0): the MONO Sikka mark for the ink mail header
         *  (null when unset — the header simply carries no unit mark). */
        public readonly ?string $sikkaMonoUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(to: $this->recipient, subject: 'Reset your '.$this->siteName.' password');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.auth.reset-password',
            with: [
                'url' => $this->url,
                'minutes' => $this->minutes,
                'siteName' => $this->siteName,
                'name' => $this->name,
                'sikkaMonoUrl' => $this->sikkaMonoUrl,
            ],
            // A plain-text alternative ships with it — a text-only client
            // must still receive the working link.
            text: 'mail.auth.reset-password-text',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
