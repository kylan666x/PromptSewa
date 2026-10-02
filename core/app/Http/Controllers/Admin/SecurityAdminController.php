<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BotChallengeService;
use App\Services\SettingsService;
use Illuminate\Http\Request;

/**
 * T6 (v1.7.3) — Admin → Security: bot-challenge provider, keys, per-form
 * enables and the disposable-domain blocklist. Admin-only (moderators 403).
 * Ships with its nav pill in the same commit (§6.36 watch-out).
 */
class SecurityAdminController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly BotChallengeService $bot,
    ) {}

    public function edit(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return view('admin.security', [
            'provider' => (string) $this->settings->get('captcha_provider', ''),
            'siteKey' => $this->bot->siteKey(),
            'secretSaved' => $this->settings->get('captcha_secret') !== null && $this->settings->get('captcha_secret') !== '',
            'minScore' => (string) $this->settings->get('captcha_min_score', '0.5'),
            'failOpen' => $this->bot->failOpen(),
            'forms' => BotChallengeService::FORMS,
            'formEnables' => collect(BotChallengeService::FORMS)
                ->mapWithKeys(fn (string $form) => [$form => $this->bot->enabledFor($form)]),
            'blockedExtra' => (string) $this->settings->get('blocked_domains_extra', ''),
            'bundledCount' => count(config('disposable-domains', [])),
            'blockOnReports' => $this->settings->isOn('block_disposable_on_reports'),
        ]);
    }

    public function update(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'captcha_provider' => ['nullable', 'in:,turnstile,recaptcha_v3'],
            'captcha_site_key' => ['nullable', 'string', 'max:255'],
            'captcha_secret' => ['nullable', 'string', 'max:512'],
            'captcha_min_score' => ['nullable', 'numeric', 'between:0,1'],
        ]);

        foreach (['captcha_provider', 'captcha_site_key', 'captcha_min_score'] as $key) {
            $this->settings->set($key, $validated[$key] ?? '');
        }

        // Write-only secret: empty input keeps the saved one.
        if (($validated['captcha_secret'] ?? '') !== '') {
            $this->settings->set('captcha_secret', $validated['captcha_secret']);
        }

        $this->settings->set('captcha_fail_open', $request->boolean('captcha_fail_open') ? '1' : '0');
        $this->settings->set('block_disposable_on_reports', $request->boolean('block_disposable_on_reports') ? '1' : '0');

        foreach (BotChallengeService::FORMS as $form) {
            $this->settings->set('captcha_form_'.$form, $request->boolean('captcha_form_'.$form) ? '1' : '0');
        }

        // Blocklist textarea: one domain per line (or comma-separated).
        $this->settings->set('blocked_domains_extra', trim((string) $request->input('blocked_domains_extra', '')));

        return back()->with('success', 'Security settings saved.');
    }

    /** T6: "test an address" endpoint — blocked/ok against the merged list. */
    public function testEmail(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $email = mb_strtolower(trim((string) $request->input('email', '')));
        $domain = substr((string) strrchr($email, '@'), 1);

        $blocked = app('disposable.domains');

        $hit = collect($blocked)->first(
            fn (string $banned) => $domain === $banned || str_ends_with($domain, '.'.$banned)
        );

        return response()->json([
            'email' => $email,
            'result' => $hit !== null ? 'blocked' : 'ok',
            'matched' => $hit,
        ]);
    }
}
