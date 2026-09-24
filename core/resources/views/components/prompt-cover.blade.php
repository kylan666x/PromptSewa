@props(['prompt', 'large' => false])

@php
    // Deterministic placeholder: same prompt always renders the same
    // gradient — no random flicker between page loads. Palette derives
    // from DESIGN.md: ink + saffron + warm paper tints.
    $palettes = [
        ['from-saffron/60', 'to-saffron-deep/20', 'text-ink/50'],
        ['from-ink/80', 'to-ink/30', 'text-paper/70'],
        ['from-orange-300/50', 'to-amber-200/30', 'text-ink/50'],
        ['from-stone-400/40', 'to-stone-200/30', 'text-ink/50'],
        ['from-yellow-400/50', 'to-amber-100/40', 'text-ink/50'],
        ['from-ink-soft/70', 'to-saffron/20', 'text-paper/70'],
    ];
    $palette = $palettes[$prompt->id % count($palettes)];
    $initials = collect(explode(' ', $prompt->title))
        ->filter()
        ->map(fn ($word) => mb_substr($word, 0, 1))
        ->take(2)
        ->implode('');
@endphp

<div class="relative {{ $large ? 'aspect-[16/9]' : 'aspect-[16/10]' }} w-full overflow-hidden {{ $attributes->only('class') }}">
    @if ($prompt->cover_image_path)
        <img src="{{ Storage::url($prompt->cover_image_path) }}" alt="Cover for {{ $prompt->title }}"
             class="absolute inset-0 size-full object-cover">
    @else
        <div class="absolute inset-0 bg-gradient-to-br {{ $palette[0] }} {{ $palette[1] }}">
            <div class="absolute inset-0 opacity-40 [background-image:radial-gradient(circle_at_1px_1px,rgba(23,23,21,0.18)_1px,transparent_0)] [background-size:14px_14px]"></div>
            <div class="absolute inset-0 flex items-center justify-center">
                <span class="{{ $large ? 'text-6xl' : 'text-4xl' }} font-bold tracking-tight {{ $palette[2] }}">{{ $initials }}</span>
            </div>
        </div>
    @endif
</div>
