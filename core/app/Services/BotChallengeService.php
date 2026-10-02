<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * T1/T2 (v1.7.3) — Bot Challenge.
 *
 * Drivers: null (pass-through, zero markup, always pass) | turnstile |
 * recaptcha_v3. All configuration lives in SettingsService:
 *
 *   captcha_provider        — '' (null), 'turnstile', 'recaptcha_v3'
 *   captcha_site_key        — plaintext ok (public value)
 *   captcha_secret          — SECRET_KEY (Crypt::encryptString at rest)
 *   captcha_min_score       — v3 only, default 0.5
 *   captcha_fail_open       — default FALSE = fail-closed on transport error
 *   captcha_form_*          — per-form enables (register on by default)
 *
 * T2: verification is SERVER-SIDE ONLY (Http::timeout(10)->retry(1) POST
 * to the provider's siteverify endpoint); the client token is never
 * trusted. v3 additionally requires score >= min AND action == form name.
 * Transport errors reject unless captcha_fail_open is on. Outcomes are
 * logged masked at info — never the secret, never the full token.
 */
class BotChallengeService
{
    /** Forms that can opt into the challenge. */
    public const FORMS = ['register', 'login', 'reset', 'submit', 'report'];

    public function __construct(private readonly SettingsService $settings) {}

    /** Active driver: 'turnstile' | 'recaptcha_v3' | null. */
    public function provider(): ?string
    {
        $provider = strtolower(trim((string) $this->settings->get('captcha_provider', '')));

        return in_array($provider, ['turnstile', 'recaptcha_v3'], true) ? $provider : null;
    }

    /** Is the challenge active for a given form? */
    public function enabledFor(string $form): bool
    {
        if (! in_array($form, self::FORMS, true)) {
            return false;
        }

        if ($this->provider() === null) {
            return false;
        }

        // register on by default; every other form off unless switched on.
        $default = $form === 'register' ? '1' : '0';

        return $this->settings->isOn('captcha_form_'.$form) || $this->settings->get('captcha_form_'.$form, $default) === '1';
    }

    public function siteKey(): string
    {
        return (string) $this->settings->get('captcha_site_key', '');
    }

    public function minScore(): float
    {
        return (float) ($this->settings->get('captcha_min_score', '0.5') ?: '0.5');
    }

    public function failOpen(): bool
    {
        return $this->settings->isOn('captcha_fail_open');
    }

    /**
     * Verify the submitted token for a form. Forms without the challenge
     * pass unconditionally. Returns true = accept, false = reject.
     */
    public function verify(string $form, ?string $token): bool
    {
        if (! $this->enabledFor($form)) {
            return true; // pass-through: disabled or no provider
        }

        $provider = $this->provider();
        $secret = (string) $this->settings->get('captcha_secret', '');

        if ($secret === '' || trim((string) $token) === '') {
            Log::info('bot-challenge rejected', ['form' => $form, 'reason' => 'missing token/secret']);

            return false;
        }

        try {
            $endpoint = config('captcha.endpoints.'.$provider);

            $payload = $provider === 'turnstile'
                ? ['secret' => $secret, 'response' => $token]
                : ['secret' => $secret, 'response' => $token];

            $response = Http::asForm()
                ->timeout(10)
                ->retry(1, 100)
                ->post($endpoint, $payload);

            $data = $response->json() ?? [];

            if (! ($data['success'] ?? false)) {
                Log::info('bot-challenge rejected', ['form' => $form, 'reason' => 'provider said no', 'codes' => count($data['error-codes'] ?? [])]);

                return false;
            }

            // T2: v3 additionally checks score AND action.
            if ($provider === 'recaptcha_v3') {
                $score = (float) ($data['score'] ?? 0);
                $action = (string) ($data['action'] ?? '');

                if ($score < $this->minScore() || $action !== $form) {
                    Log::info('bot-challenge rejected', ['form' => $form, 'reason' => 'score/action']);

                    return false;
                }
            }

            Log::info('bot-challenge passed', ['form' => $form, 'provider' => $provider]);

            return true;
        } catch (\Throwable $e) {
            // Transport error: fail-closed by default; captcha_fail_open
            // flips it for degraded-mode availability.
            Log::info('bot-challenge transport error', ['form' => $form, 'fail_open' => $this->failOpen()]);

            return $this->failOpen();
        }
    }
}
