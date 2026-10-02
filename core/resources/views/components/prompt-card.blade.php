@props(['prompt', 'saved' => false])

@php
    /** @var \App\Models\Prompt $prompt */
    $tags = collect($prompt->latestVersion?->tags ?? [])->take(3);
@endphp

{{-- G1 (v1.7.4): NO overflow-hidden on the card root — the creator's
     frame protrudes outside the avatar box and a clipping root would eat
     the ornament. The cover bleed clips on x-prompt-cover itself. --}}
<article class="group relative flex flex-col rounded-2xl border border-ink/10 bg-white shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-saffron-deep hover:shadow-card-hover">
    <a href="{{ route('prompts.show', $prompt) }}" class="focus:outline-none" aria-label="View {{ $prompt->title }}">
        <x-prompt-cover :prompt="$prompt"/>
    </a>
    <div class="flex flex-1 flex-col gap-3 p-5">
        <div class="flex items-start justify-between gap-3">
            <h3 class="font-semibold leading-snug text-ink">
                <a href="{{ route('prompts.show', $prompt) }}" class="transition group-hover:text-saffron-deep">
                    {{ $prompt->title }}
                </a>
            </h3>
            <span class="shrink-0 rounded-full px-2.5 py-1 font-mono text-xs font-bold {{ $prompt->price_cents === 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-saffron/25 text-ink' }}">
                {{ $prompt->priceLabel() }}
            </span>
        </div>

        {{-- T13 (v1.5.0) / P1 (v1.7.1): bookmark heart — Alpine optimistic
             flip, no reload. Saved = FILLED ROSE (text-rose-600 fill-current,
             aria-pressed true); unsaved = outline ink/40. Guests get a plain
             link to login; $saved pre-flips the state server-side. --}}
        @auth
            <button type="button"
                    x-data="bookmarkHeart({{ Js::from(['url' => route('bookmarks.toggle', $prompt), 'saved' => $saved]) }})"
                    @click="toggle()"
                    :aria-pressed="saved"
                    aria-label="Save this prompt"
                    class="absolute right-4 top-4 z-10 flex size-9 items-center justify-center rounded-full bg-white/90 shadow-sm transition hover:scale-105 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-saffron">
                <svg class="size-5 transition"
                     :class="saved ? 'text-rose-600 fill-current' : 'text-ink/40 fill-none'"
                     xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z"/>
                </svg>
            </button>
        @endauth

        <p class="line-clamp-2 text-sm leading-relaxed text-ink/60">{{ $prompt->description }}</p>

        <div class="flex flex-wrap items-center gap-1.5">
            @if ($prompt->category)
                <x-category-badge :category="$prompt->category"/>
            @endif
            @foreach ($tags as $tag)
                <span class="rounded-md border border-ink/10 bg-paper-deep px-2 py-0.5 font-mono text-[11px] text-ink/60">{{ $tag }}</span>
            @endforeach
        </div>

        <div class="mt-auto flex items-center justify-between border-t border-ink/10 pt-3">
            @if ($prompt->creator)
                <a href="{{ route('creators.show', $prompt->creator) }}" class="group/creator flex items-center gap-2 rounded-full transition focus:outline-none focus-visible:ring-2 focus-visible:ring-saffron-deep" aria-label="View profile of {{ $prompt->creator->name }}">
                    {{-- W1 (v1.7.3): frame parity — cards carry the creator's frame too. --}}
                    <x-user-avatar :user="$prompt->creator" size="sm" :frame="$prompt->creator->activeFrame" class="transition group-hover/creator:bg-saffron group-hover/creator:text-ink"/>
                    <span class="flex items-center gap-1 text-xs transition group-hover/creator:text-ink group-hover/creator:underline decoration-saffron decoration-2 underline-offset-2"><x-user-handle :user="$prompt->creator" size="text-xs"/> <x-verified-badge :user="$prompt->creator" size="xs"/></span>
                </a>
            @else
                <div class="flex items-center gap-2">
                    <span class="flex size-6 items-center justify-center rounded-full bg-ink text-[10px] font-bold text-saffron">?</span>
                    <span class="text-xs text-ink/60">Unknown</span>
                </div>
            @endif
            <div class="flex items-center gap-1 font-mono text-xs text-ink/50" title="Community rating coming soon">
                <svg class="size-3.5 text-saffron-deep" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z" clip-rule="evenodd"/>
                </svg>
                <span>New</span>
            </div>
        </div>
    </div>
</article>
