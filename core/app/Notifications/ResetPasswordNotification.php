<?php

namespace App\Notifications;

use App\Mail\ResetPasswordMail;
use App\Services\SettingsService;
use Illuminate\Auth\Notifications\ResetPassword;
use Throwable;

/**
 * A1 (v1.7.6) — the password-reset email.
 *
 * Replaces Laravel's markdown reset mail with the PromptSewa paper-world
 * template: an ink header bar, a saffron call-to-action and a plain-text
 * alternative for clients that do not render HTML.
 *
 * Two hard rules encoded here:
 *  - the body NEVER carries the account's password — only the broker token
 *    (see PasswordResetTest);
 *  - the site name falls back to config when the settings table is
 *    unreachable, so a mail render can never 500 the reset flow.
 */
class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): ResetPasswordMail
    {
        $siteName = $this->siteName();

        return new ResetPasswordMail(
            recipient: $notifiable->getEmailForPasswordReset(),
            url: url(route('password.reset', [
                'token' => $this->token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ])),
            minutes: (int) config('auth.passwords.users.expire', 60),
            siteName: $siteName,
            name: (string) ($notifiable->name ?? $notifiable->getEmailForPasswordReset()),
            // S9 (v1.8.0): mail headers use the MONO Sikka mark; unset (or
            // economy off) means no unit mark, never a broken image.
            sikkaMonoUrl: $this->sikkaMonoUrl(),
        );
    }

    /** The mono mark's public URL, or null — a settings failure never breaks mail. */
    private function sikkaMonoUrl(): ?string
    {
        try {
            $settings = app(SettingsService::class);

            if (! $settings->isOn('sikka_enabled')) {
                return null;
            }

            $path = (string) ($settings->get('sikka-icon-mono-path', '') ?? '');

            return $path !== '' ? asset('storage/'.$path) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** Never let a settings failure break the mail rail. */
    private function siteName(): string
    {
        try {
            return app(SettingsService::class)->siteName();
        } catch (Throwable) {
            return (string) config('app.name', 'PromptSewa');
        }
    }
}
