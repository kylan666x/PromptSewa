<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A3 (v1.7.6) — runtime mail configuration.
 *
 * The whole point: a cPanel host's mail is decided in the admin panel, not
 * in .env. config/mail.php ships `sendmail` (cPanel's Exim) as the default
 * so auth mail works with ZERO configuration on a fresh install; this
 * service only steps in when the founder has actually saved settings.
 *
 * Encryption mapping (config/auth.php no longer has an `encryption` key —
 * Symfony DSN schemes do that work):
 *
 *   ssl  → smtps             implicit TLS, the babal.host/465 default
 *   tls  → smtp + require_tls STARTTLS demanded, refused if unsupported
 *   none → smtp + auto_tls=0  plain relay (localhost mail daemons only)
 *
 * Applied at BOOT (AppServiceProvider), before the first mail is resolved,
 * so every mail in the request — reset links, and later the v1.7.7
 * receipts — travels on the configured rail. `Mail::purge()` after a save
 * drops the already-resolved mailer so the admin's test probe uses the
 * configuration just typed, not the one the request booted with.
 */
class MailConfigService
{
    public const MAILERS = ['sendmail', 'smtp', 'log'];

    public const ENCRYPTIONS = ['ssl', 'tls', 'none'];

    /** Settings that describe the SMTP transport. */
    public const KEYS = [
        'mail_mailer',
        'mail_host',
        'mail_port',
        'mail_encryption',
        'mail_username',
        'mail_password',
        'mail_from_address',
        'mail_from_name',
    ];

    public function __construct(private readonly SettingsService $settings) {}

    /**
     * Overwrite config('mail.*') from the settings store.
     *
     * Returns false (and changes NOTHING) when the admin has never saved
     * mail settings — the zero-config sendmail default stands. Never
     * throws: this runs during boot, where a missing `settings` table
     * (fresh install, `php artisan migrate`) must not take the app down.
     */
    public function apply(): bool
    {
        try {
            // ONE query for the whole block — this runs on every boot.
            $stored = \App\Models\Setting::query()
                ->whereIn('key', self::KEYS)
                ->pluck('key')
                ->all();

            if ($stored === []) {
                return false;
            }

            $mailer = $this->mailer();
            config(['mail.default' => $mailer]);

            if ($mailer === 'smtp') {
                config(['mail.mailers.smtp' => array_merge(
                    config('mail.mailers.smtp', []),
                    $this->smtpTransportConfig(),
                )]);
            }

            config(['mail.from' => $this->from()]);

            return true;
        } catch (Throwable $e) {
            Log::warning('mail settings not applied (settings store unavailable)', ['class' => $e::class]);

            return false;
        }
    }

    /** The selected mailer, or the config default when unset. */
    public function mailer(): string
    {
        $configured = strtolower(trim((string) $this->settings->get('mail_mailer', '')));

        return in_array($configured, self::MAILERS, true)
            ? $configured
            : (string) config('mail.default', 'sendmail');
    }

    /** True when an SMTP password is on file (never the password itself). */
    public function passwordSaved(): bool
    {
        $value = (string) $this->settings->get('mail_password', '');

        return $value !== '';
    }

    /**
     * The smtp transport block: host/port/credentials from settings, plus
     * the scheme/auto_tls/require_tls trio derived from mail_encryption.
     */
    private function smtpTransportConfig(): array
    {
        $encryption = strtolower(trim((string) $this->settings->get('mail_encryption', 'ssl')));
        $encryption = in_array($encryption, self::ENCRYPTIONS, true) ? $encryption : 'ssl';

        $port = (int) $this->settings->get('mail_port', '465');
        $host = trim((string) $this->settings->get('mail_host', ''));

        $transport = [
            'transport' => 'smtp',
            'host' => $host !== '' ? $host : config('mail.mailers.smtp.host'),
            'port' => $port > 0 ? $port : 465,
            'username' => (string) $this->settings->get('mail_username', ''),
            'password' => (string) $this->settings->get('mail_password', ''),
        ];

        if ($encryption === 'ssl') {
            $transport['scheme'] = 'smtps';
        } else {
            $transport['scheme'] = 'smtp';
        }

        // Both switches are ALWAYS written. Setting only the relevant one
        // left the previous value behind — an admin switching tls → none
        // would keep require_tls and get a connection refused forever.
        $transport['auto_tls'] = $encryption !== 'none';
        $transport['require_tls'] = $encryption === 'tls';

        return $transport;
    }

    /**
     * From-address falls back down the chain: the mail settings → the Brand
     * contact email → whatever .env/config said. A reset mail that goes
     * out as "hello@example.com" gets filtered as spam, so the Brand
     * fallback is not cosmetic.
     */
    private function from(): array
    {
        $address = trim((string) $this->settings->get('mail_from_address', ''));
        $name = trim((string) $this->settings->get('mail_from_name', ''));

        if ($address === '') {
            $address = trim((string) $this->settings->get('contact_email', ''));
        }

        if ($name === '') {
            $name = $this->settings->siteName();
        }

        return [
            'address' => $address !== '' ? $address : (string) config('mail.from.address'),
            'name' => $name !== '' ? $name : (string) config('mail.from.name'),
        ];
    }
}