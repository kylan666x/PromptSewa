@props([
    'variant' => 'primary',
    'type' => 'button',
    'href' => null,
])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-full px-4 py-2.5 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-saffron-deep focus-visible:ring-offset-2 focus-visible:ring-offset-paper';
    $variants = [
        'primary' => 'bg-saffron text-ink shadow-sm hover:bg-saffron-deep',
        'secondary' => 'border border-ink/15 bg-white text-ink hover:border-ink/30',
        'ghost' => 'text-ink/70 hover:bg-ink/5 hover:text-ink',
        'danger' => 'bg-rose-600 text-white hover:bg-rose-500',
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
