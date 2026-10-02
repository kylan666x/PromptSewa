<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Runtime settings store with encrypted secrets.
 *
 * - Plain settings (site name, toggles) are stored as-is.
 * - Secret settings (payment keys) are stored encrypted and are NEVER
 *   echoed back to views — forms show a "saved" placeholder instead.
 * - Values are cached per-request to avoid repeated queries.
 */
class SettingsService
{
    /** Settings that hold credentials — always encrypted at rest. */
    public const SECRET_KEYS = [
        'esewa_secret_key',
        'esewa_sandbox_secret_key',
        // T1 (v1.7.3): bot-challenge secret (Cloudflare Turnstile / reCAPTCHA).
        'captcha_secret',
    ];

    /** Well-known settings with defaults. */
    public const DEFAULTS = [
        'site_name' => 'PromptSewa',
        'site_tagline' => 'Discover, test, buy and sell premium AI prompts.',
        'brand_logo_path' => '',
        'brand_mark_path' => '',
        'brand_favicon_path' => '',
        'contact_email' => '',
        'support_email' => '',
        'about_page' => '',
        'payments_enabled' => '0',
        'esewa_enabled' => '0',
        'esewa_merchant_code' => '',
        'esewa_base_url' => 'https://rc.esewa.com.np',
        // M3 (v1.6.0): sandbox rail — swaps merchant code + secret, never
        // skips signature verification.
        'esewa_sandbox' => '0',
        'esewa_sandbox_merchant_code' => 'EPAYTEST',
        'esewa_sandbox_secret_key' => '',
        'manual_payment_enabled' => '0',
        // v1.4.4: the admin form's checkbox field is named manual_enabled,
        // but the canonical runtime key is manual_payment_enabled — the C2
        // bug was the controller persisting under the form-field name.
        // (A legacy manual_enabled row may exist in old installs; nothing
        // reads it anymore.)
        'manual_payment_instructions' => '',

        // T1 (v1.7.3): bot challenge. Provider '' = disabled (pass-through);
        // register is ON by default, every other form off.
        'captcha_provider' => '',
        'captcha_site_key' => '',
        'captcha_min_score' => '0.5',
        'captcha_fail_open' => '0',
        'captcha_form_register' => '1',
        'captcha_form_login' => '0',
        'captcha_form_reset' => '0',
        'captcha_form_submit' => '0',
        'captcha_form_report' => '0',

        // T5 (v1.7.3): disposable-mail gate. Extra admin-blocked domains
        // (newline textarea); the curated bundle lives in config/disposable-domains.php.
        'blocked_domains_extra' => '',
        'block_disposable_on_reports' => '0',
    ];

    public function get(string $key, ?string $default = null): ?string
    {
        // No per-instance cache: reads hit the DB directly. The settings
        // table is tiny and this guarantees read-after-write consistency
        // for long-lived instances (octane workers, queues, tests).
        $row = Setting::query()->find($key);

        $value = $row?->value;

        if ($value !== null && in_array($key, self::SECRET_KEYS, true)) {
            $value = $this->decrypt($value);
        }

        return $value ?? $default;
    }

    public function set(string $key, ?string $value): void
    {
        if (in_array($key, self::SECRET_KEYS, true)) {
            $value = $value === null || $value === '' ? null : $this->encrypt($value);
        }

        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * The site name with the configured value falling back to the app
     * config — used by the layout, navbar and footer.
     */
    public function siteName(): string
    {
        return (string) ($this->get('site_name') ?: config('app.name', 'PromptSewa'));
    }

    /**
     * Boolean helper (checked checkboxes arrive as "1"/"on").
     */
    public function isOn(string $key): bool
    {
        return in_array(strtolower((string) $this->get($key, '0')), ['1', 'on', 'true', 'yes'], true);
    }

    private function encrypt(string $value): string
    {
        return Crypt::encryptString($value);
    }

    private function decrypt(string $value): ?string
    {
        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            return null; // key rotation or corrupt row — treat as unset
        }
    }
}
