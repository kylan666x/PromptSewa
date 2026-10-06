@props(['amount' => 0])

{{--
    S1 (v1.9.0) — the always-visible Sikka wallet chip.

    Mounted in the navbar for signed-in users on BOTH viewports (desktop
    cluster + the mobile top row). Clicking it lands on the earnings tab —
    the Sikka ledger — so the balance is one tap from every page.

    Format contract: the mark + the grouped integer, nothing else. The
    amount crosses as an int and <x-sikka> is the sole renderer; this
    component adds no money formatting of its own.
--}}
<a href="{{ route('dashboard.earnings') }}"
   data-testid="nav-sikka-chip"
   title="Your Sikka balance"
   aria-label="Sikka balance — open your earnings ledger"
   {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center gap-1.5 rounded-full border border-saffron-deep/40 bg-saffron/20 px-2.5 py-1.5 font-mono text-xs font-bold text-ink transition hover:bg-saffron/40']) }}>
    <x-sikka :amount="(int) $amount"/>
</a>
