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
    ];

    /** Well-known settings with defaults. */
    public const DEFAULTS = [
        'site_name' => 'PromptSewa',
        'site_tagline' => 'Discover, test, buy and sell premium AI prompts.',
        'brand_logo_path' => '',
        'brand_favicon_path' => '',
        'contact_email' => '',
        'support_email' => '',
        'about_page' => '',
        'payments_enabled' => '0',
        'esewa_enabled' => '0',
        'esewa_merchant_code' => '',
        'esewa_base_url' => 'https://rc.esewa.com.np',
        'manual_payment_enabled' => '0',
        'manual_payment_instructions' => '',
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
