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
        );
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