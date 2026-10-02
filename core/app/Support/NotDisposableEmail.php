<?php

namespace App\Support;

use Illuminate\Contracts\Validation\InvokableRule;
use Illuminate\Support\Facades\App;

/**
 * T5 (v1.7.3) — disposable-email gate.
 *
 * Blocks throwaway-inbox domains: exact domain OR any subdomain,
 * case-insensitive. The curated bundle lives in config/disposable-domains.php;
 * the admin extends it at runtime via the `blocked_domains_extra` setting
 * (newline-separated textarea). Controllers never hardcode domain lists.
 *
 * Usage: 'email' => ['required', 'email', new NotDisposableEmail()]
 * (the report form only applies it when block_disposable_on_reports is on).
 */
class NotDisposableEmail implements InvokableRule
{
    public function __invoke(string $attribute, mixed $value, \Closure $fail): void
    {
        $email = mb_strtolower(trim((string) $value));
        $domain = substr((string) strrchr($email, '@'), 1);

        if ($domain === '' || $domain === false) {
            $fail('Disposable email addresses are not allowed — use a permanent inbox.');

            return;
        }

        $blocked = App::make('disposable.domains');

        foreach ($blocked as $banned) {
            if ($domain === $banned || str_ends_with($domain, '.'.$banned)) {
                $fail('Disposable email addresses are not allowed — use a permanent inbox.');

                return;
            }
        }
    }
}
