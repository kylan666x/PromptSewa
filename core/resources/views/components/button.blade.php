@props([
    'variant' => 'primary',
    'type' => 'button',
    'href' => null,
])

@php
    // Game-feel: chunky offset shadow that "presses" into the page on
    // hover/active — tactile arcade-button energy in brand colors.
    $base = 'inline-flex items-center justify-center gap-2 rounded-full px-4 py-2.5 text-sm font-bold uppercase tracking-wide transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-saffron-deep focus-visible:ring-offset-2 focus-visible:ring-offset-paper active:translate-y-0.5 active:shadow-none';
    $variants = [
        'primary' => 'bg-saffron text-ink shadow-[0_4px_0_0_#a16207] hover:-translate-y-0.5 hover:shadow-[0_6px_0_0_#a16207] active:shadow-none',
        'secondary' => 'border-2 border-ink/80 bg-white text-ink shadow-[0_4px_0_0_rgba(23,23,21,0.8)] hover:-translate-y-0.5 hover:shadow-[0_6px_0_0_rgba(23,23,21,0.8)] active:shadow-none',
        'ghost' => 'text-ink/70 hover:bg-ink/5 hover:text-ink',
        'danger' => 'bg-rose-600 text-white shadow-[0_4px_0_0_#7f1d1d] hover:-translate-y-0.5 hover:shadow-[0_6px_0_0_#7f1d1d] active:shadow-none',
    ];
    $classes = $base.' '.($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
