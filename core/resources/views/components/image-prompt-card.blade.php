@props(['prompt'])

@php
    /** @var \App\Models\Prompt $prompt */
    $body = (string) $prompt->latestVersion?->body;
    // Card teaser: first ~180 chars of the prompt, word-safe.
    $snippet = mb_strlen($body) > 180
        ? mb_substr($body, 0, 180).'…'
        : $body;
    $palettes = [
        ['from-saffron/60', 'to-saffron-deep/20', 'text-ink/50'],
        ['from-ink/80', 'to-ink/30', 'text-paper/70'],
        ['from-orange-300/50', 'to-amber-200/30', 'text-ink/50'],
        ['from-stone-400/40', 'to-stone-200/30', 'text-ink/50'],
        ['from-yellow-400/50', 'to-amber-100/40', 'text-ink/50'],
        ['from-ink-soft/70', 'to-saffron/20', 'text-paper/70'],
    ];
    $palette = $palettes[$prompt->id % count($palettes)];
    $initials = collect(explode(' ', $prompt->title))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('');
@endphp

<article class="group relative flex flex-col overflow-hidden rounded-2xl border border-ink/10 bg-white shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-saffron-deep hover:shadow-card-hover">

    {{-- Cover image — full-bleed, category pill overlaid --}}
    <a href="{{ route('prompts.show', $prompt) }}" class="relative block focus:outline-none" aria-label="View {{ $prompt->title }}">
        <div class="relative aspect-[4/5] w-full overflow-hidden">
            @if ($prompt->cover_image_path)
                <img src="{{ Storage::url($prompt->cover_image_path) }}" alt="{{ $prompt->title }}"
                     class="absolute inset-0 size-full object-cover transition duration-300 group-hover:scale-[1.03]"
                     loading="lazy">
            @else
                <div class="absolute inset-0 bg-gradient-to-br {{ $palette[0] }} {{ $palette[1] }}">
                    <div class="absolute inset-0 opacity-40 [background-image:radial-gradient(circle_at_1px_1px,rgba(23,23,21,0.18)_1px,transparent_0)] [background-size:14px_14px]"></div>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span class="text-5xl font-bold tracking-tight {{ $palette[2] }}">{{ $initials }}</span>
                    </div>
                </div>
            @endif

            {{-- Category pill — top-left, white on image like the reference --}}
            @if ($prompt->category)
                <span class="absolute left-3 top-3 rounded-full bg-white/95 px-3 py-1.5 font-mono text-[11px] font-bold uppercase tracking-wider text-ink shadow-sm">
                    {{ $prompt->category->name }}
                </span>
            @endif

            {{-- Price pill — top-right --}}
            <span class="absolute right-3 top-3 rounded-full px-2.5 py-1 font-mono text-[11px] font-bold shadow-sm {{ $prompt->price_cents === 0 ? 'bg-emerald-500 text-white' : 'bg-saffron text-ink' }}">
                {{ $prompt->priceLabel() }}
            </span>
        </div>
    </a>

    {{-- Prompt panel — title + visible prompt snippet + copy (the reference's signature) --}}
    <div class="flex flex-1 flex-col gap-2.5 p-4">
        <h3 class="font-semibold leading-snug text-ink">
            <a href="{{ route('prompts.show', $prompt) }}" class="transition group-hover:text-saffron-deep">
                {{ $prompt->title }}
            </a>
        </h3>

        <p class="line-clamp-3 font-mono text-xs leading-relaxed text-ink/60" title="Preview of the prompt">{{ $snippet }}</p>

        <div class="mt-auto flex items-center justify-between gap-2 pt-2">
            {{-- Copy button — copies the raw prompt body (free) or links to detail (paid) --}}
            @if ($prompt->price_cents === 0 && $body !== '')
                <button type="button"
                        x-data="promptCopy(@js($body))"
                        @click="copy()"
                        class="inline-flex items-center gap-1.5 rounded-full bg-ink px-3.5 py-2 font-mono text-xs font-bold text-paper transition hover:bg-saffron hover:text-ink"
                        aria-live="polite">
                    <svg class="size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184"/>
                    </svg>
                    <span x-text="copied ? 'Copied!' : 'Copy'"></span>
                </button>
            @else
                <a href="{{ route('prompts.show', $prompt) }}"
                   class="inline-flex items-center gap-1.5 rounded-full bg-saffron px-3.5 py-2 font-mono text-xs font-bold text-ink transition hover:bg-saffron-deep">
                    {{ $prompt->priceLabel() }} — view
                </a>
            @endif

            @if ($prompt->creator)
                <a href="{{ route('creators.show', $prompt->creator) }}" class="flex min-w-0 items-center gap-1.5 text-xs text-ink/50 transition hover:text-ink">
                    <x-user-avatar :user="$prompt->creator" size="xs" :frame="$prompt->creator->activeFrame"/>
                    <span class="flex items-center gap-1 truncate"><x-user-handle :user="$prompt->creator" size="text-xs"/> <x-verified-badge :user="$prompt->creator" size="xs"/></span>
                </a>
            @endif
        </div>
    </div>
</article>
