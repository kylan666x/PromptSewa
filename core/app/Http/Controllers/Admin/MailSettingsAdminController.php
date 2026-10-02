<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TestEmail;
use App\Services\MailConfigService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * A3 (v1.7.6) — Admin → Email.
 *
 * Mailer selection + SMTP credentials for the babal.host class of host,
 * with a "send a test to yourself" probe so the founder never has to guess
 * whether a reset link went anywhere. Admin-only (moderators 403); ships
 * with its nav pill in the same commit (§6.36 watch-out).
 *
 * Two rules the panel enforces:
 *  - the SMTP password is WRITE-ONLY. It is encrypted at rest in
 *    SettingsService::SECRET_KEYS, never echoed into the form, and an empty
 *    submission keeps whatever is saved;
 *  - a failed send reports the exception CLASS and its first line only,
 *    with the password scrubbed out of the message — an SMTP failure is
 *    usually "authentication failed" and useless as a password oracle.
 */
class MailSettingsAdminController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly MailConfigService $mail,
    ) {}

    public function edit(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return view('admin.email', [
            'mailer' => $this->settings->get('mail_mailer', '') ?: (string) config('mail.default'),
            'mailers' => MailConfigService::MAILERS,
            'encryptions' => MailConfigService::ENCRYPTIONS,
            'host' => (string) $this->settings->get('mail_host', ''),
            'port' => (string) $this->settings->get('mail_port', '465'),
            'encryption' => (string) $this->settings->get('mail_encryption', 'ssl'),
            'username' => (string) $this->settings->get('mail_username', ''),
            'passwordSaved' => $this->mail->passwordSaved(),
            'fromAddress' => (string) $this->settings->get('mail_from_address', ''),
            'fromName' => (string) $this->settings->get('mail_from_name', ''),
            'activeMailer' => (string) config('mail.default'),
            'activeHost' => (string) config('mail.mailers.smtp.host', ''),
            'activePort' => (int) config('mail.mailers.smtp.port', 0),
            'brandFallback' => (string) $this->settings->get('contact_email', ''),
            'siteName' => $this->settings->siteName(),
        ]);
    }

    public function update(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'mail_mailer' => ['required', 'in:'.implode(',', MailConfigService::MAILERS)],
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'integer', 'between:1,65535'],
            'mail_encryption' => ['required', 'in:'.implode(',', MailConfigService::ENCRYPTIONS)],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:512'],
            'mail_from_address' => ['nullable', 'string', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:120'],
        ]);

        foreach (['mail_mailer', 'mail_host', 'mail_port', 'mail_encryption', 'mail_username', 'mail_from_address', 'mail_from_name'] as $key) {
            $this->settings->set($key, trim((string) ($validated[$key] ?? '')));
        }

        // Write-only secret: an empty box keeps the saved one.
        if (($validated['mail_password'] ?? '') !== '') {
            $this->settings->set('mail_password', $validated['mail_password']);
        }

        // Drop the already-resolved mailer so THIS request — and the test
        // probe below — uses the configuration just saved.
        $this->mail->apply();
        Mail::purge();

        return back()->with('success', 'Email settings saved.');
    }

    /** The probe: send the configured rail a real message to the admin. */
    public function testEmail(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->isAdmin(), 403);

        // Re-apply: the probe must test what is saved, not what this request
        // happened to boot with.
        $this->mail->apply();
        Mail::purge();

        $mailer = (string) config('mail.default');
        $smtp = (array) config('mail.mailers.smtp', []);

        try {
            Mail::send(new TestEmail(
                recipient: $user->email,
                rail: $mailer,
                host: (string) ($smtp['host'] ?? ''),
                port: (int) ($smtp['port'] ?? 0),
                siteName: $this->settings->siteName(),
            ));

            return back()->with('success', 'Test email sent to '.$user->email.' via '.$mailer.'.');
        } catch (Throwable $e) {
            Log::warning('admin test email failed', [
                'mailer' => $mailer,
                'class' => $e::class,
                'user_id' => $user->id,
            ]);

            // The class plus ONE line, with the mailbox password scrubbed
            // out — an SMTP error string must never become a hint about it.
            $firstLine = trim((string) (preg_split('/\r?\n/', $e->getMessage())[0] ?? ''));
            $password = (string) $this->settings->get('mail_password', '');
            if ($password !== '') {
                $firstLine = str_replace($password, '•••', $firstLine);
            }

            return back()
                ->withInput()
                ->with('error', 'Send failed: '.$this->shortClass($e).($firstLine !== '' ? ' — '.$firstLine : '.'));
        }
    }

    /** TransportException, not the full namespace — a support line, not a stack. */
    private function shortClass(Throwable $e): string
    {
        return class_basename($e);
    }
}