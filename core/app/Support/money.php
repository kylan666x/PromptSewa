<?php

/**
 * M7 (v1.6.0) — the ONE way money renders in views (M2/AGENTS.md #1):
 * mono, tabular, integer paisa in → "Rs. 1,497" out. No floats anywhere.
 *
 * The M6 arch test bans raw paisa echoes in views; anything showing money
 * must call this helper.
 */
if (! function_exists('money_npr')) {
    function money_npr(int $paisa): string
    {
        $sign = $paisa < 0 ? '-' : '';

        return $sign.'Rs. '.number_format(intdiv(abs($paisa), 100)).'.'
            .str_pad((string) (abs($paisa) % 100), 2, '0', STR_PAD_LEFT);
    }
}

/**
 * v1.6.1 hotfix (prod 500 root cause): composer autoload.files only takes
 * effect after `composer dump-autoload`, which the cPanel host cannot run.
 * A code-only update zip therefore ships money.php but prod's
 * vendor/composer/autoload_files.php predates it — the helper is UNDEFINED
 * at runtime and every money-rendering page 500s.
 *
 * The helper is tiny and stable, so views also guard with function_exists()
 * and fall back to an identical inline implementation (see
 * components/money.blade.php). If the helper ever changes, update BOTH.
 */
if (! function_exists('money_npr_safe')) {
    /** The view-side fallback: same output as money_npr(), always defined. */
    function money_npr_safe(int $paisa): string
    {
        if (function_exists('money_npr')) {
            return money_npr($paisa);
        }

        $sign = $paisa < 0 ? '-' : '';

        return $sign.'Rs. '.number_format(intdiv(abs($paisa), 100)).'.'
            .str_pad((string) (abs($paisa) % 100), 2, '0', STR_PAD_LEFT);
    }
}
