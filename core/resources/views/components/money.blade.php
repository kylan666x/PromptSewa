@php
    /**
     * M7 (v1.6.0) / v1.6.1 hotfix — the ONLY way a view renders money.
     *
     * money_npr() normally arrives via composer autoload.files. On the
     * cPanel host a code-only update zip cannot refresh vendor/, so the
     * helper may be UNDEFINED at runtime (the prod /earnings 500 root
     * cause). This component falls back to an identical inline copy —
     * keep it in sync with app/Support/money.php if the format changes.
     */
    /** @var int $paisa */
    /** @var bool $signed */
@endphp

<span {{ $attributes->merge(['class' => 'font-mono tabular-nums']) }}>@if (($signed ?? false) && $paisa >= 0)+@endif{{ (function_exists('money_npr') ? money_npr((int) $paisa) : (($paisa < 0 ? '-' : '').'Rs. '.number_format(intdiv(abs((int) $paisa), 100)).'.'.str_pad((string) (abs((int) $paisa) % 100), 2, '0', STR_PAD_LEFT))) }}</span>
