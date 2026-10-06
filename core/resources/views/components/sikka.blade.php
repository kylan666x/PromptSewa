@props(['amount' => 0, 'mono' => false, 'word' => false, 'size' => 20])

@php
    /**
     * S6/S9 (v1.8.0) — the ONLY way a view renders a Sikka amount.
     *
     * S1 (v1.9.0) — props are DECLARED so they never leak into the element
     * as attributes (`amount="142"` on a span is not HTML anyone wants).
     *
     * Sikka is a credit, not a currency: integers only, no decimals, no
     * fiat glyph — the amount comes from SikkaFormat (PSR-4, no
     * autoload.files) and is rendered as a grouped integer.
     *
     * Icon rules (S9b): the color icon is admin-managed (Brand settings,
     * sikka-icon-path) and renders at >= 20px on both worlds; the mono
     * variant (sikka-icon-mono-path) is for mail headers and ink/plain
     * surfaces. With no icon uploaded the component falls back to an
     * honest mono bordered chip reading "Sikka" — zero broken images.
     * The first mention on a page pairs the mark with the word "Sikka"
     * (`:word="true"`); later mentions may be mark + number.
     *
     * @var int  $amount  Sikka credits (integer)
     * @var bool $mono    use the mono variant (mail headers, ink surfaces)
     * @var bool $word    first mention on the page — pair the mark with "Sikka"
     * @var int  $size    icon size in px (>= 20 per the brand contract)
     */
    $amount = (int) ($amount ?? 0);
    $mono = (bool) ($mono ?? false);
    $word = (bool) ($word ?? false);
    $size = max(20, (int) ($size ?? 20));

    $settings = app(\App\Services\SettingsService::class);
    $iconPath = (string) ($settings->get($mono ? 'sikka-icon-mono-path' : 'sikka-icon-path', '') ?? '');

    if ($iconPath === '' && $mono) {
        $iconPath = (string) ($settings->get('sikka-icon-path', '') ?? ''); // fall back to the color mark on ink
    }

    $iconUrl = $iconPath !== '' ? asset('storage/'.$iconPath) : null;
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 whitespace-nowrap font-mono tabular-nums']) }}>
    @if ($iconUrl !== null)
        <img src="{{ $iconUrl }}" alt="Sikka" width="{{ $size }}" height="{{ $size }}"
             class="inline-block object-contain" style="height: {{ $size }}px; width: {{ $size }}px;" loading="lazy">
        @if ($word)<span class="font-semibold">Sikka</span>@endif
    @else
        {{-- Honest fallback: a mono bordered chip, never a broken image. --}}
        <span class="rounded border border-current px-1 py-px text-[0.75em] font-bold uppercase tracking-wider">Sikka</span>
    @endif
    <span class="font-semibold">{{ \App\Support\SikkaFormat::render($amount) }}</span>
</span>
