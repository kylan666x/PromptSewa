<?php

namespace App\Support;

/**
 * S2 (v1.8.0) — the ONE way a Sikka amount renders.
 *
 * Sikka is a credit, not a currency: integers only, never a decimal point,
 * never a float. This class is PSR-4 on purpose — new classes resolve on
 * prod without `composer dump-autoload`, so Sikka must never ride
 * composer autoload.files (the v1.6.1 money.php trap; ReleaseHygieneTest
 * hash-locks the file list). The companion rule: money_npr stays the sole
 * NPR renderer, and this formatter never emits a fiat glyph.
 */
final class SikkaFormat
{
    /** Integer amount → grouped integer string ("1,234"). No decimals, ever. */
    public static function render(int $amount): string
    {
        $sign = $amount < 0 ? '-' : '';

        return $sign.number_format(abs($amount));
    }
}
