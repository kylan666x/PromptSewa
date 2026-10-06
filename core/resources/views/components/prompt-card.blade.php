@props(['prompt', 'saved' => null, 'large' => false])

@php
    /** @var \App\Models\Prompt $prompt */
    $tags = collect($prompt->latestVersion?->tags ?? [])->take(3);

    // H4 (v1.7.4) PRE-FLIP PARITY: the card asks the per-request
    // bookmarked-id set instead of defaulting to false. Passing :saved
    // explicitly still wins (a caller that knows better may override),
    // but no surface can silently ship an unsaved heart again.
    // NOTE: a Blade {{-- --}} comment inside @php is a parse error — use
    // PHP comments in this block.
    $saved = $saved ?? \App\Support\BookmarkedIds::contains($prompt);

    // S1 (v1.9.0): Sikka is the ONLY currency on buyer surfaces — a paid
    // card shows the credit price (the price of record) and nothing else.
    // No NPR parenthetical, no kill-switch branch.
@endphp

{{-- S4 (v1.9.0) FEED CARD: the card reads top-to-bottom like a social
     post — creator identity first, artwork, then the copy — with the
     Sikka price chip anchored bottom-right (the one buy signal).

     G1 (v1.7.4): NO overflow-hidden on the card root — the creator's
     frame protrudes outside the avatar box and a clipping root would eat
     the ornament. The cover bleed clips on x-prompt-cover itself. --}}
<article class="group relative flex flex-col rounded-2xl border border-ink/10 bg-white shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-saffron-deep hover:shadow-card-hover">
    {{-- Header row — the feed's "who posted this". pr-14 keeps the name
         clear of the floating saved-heart control at top-right. --}}
    <div class="flex items-center gap-2.5 p-4 pr-14">
        @if ($prompt->creator)
            <a href="{{ route('creators.show', $prompt->creator) }}"
               class="group/creator flex min-w-0 items-center gap-2.5 rounded-full transition focus:outline-none focus-visible:ring-2 focus-visible:ring-saffron-deep"
               aria-label="View profile of {{ $prompt->creator->name }}">
                {{-- W1 (v1.7.3): frame parity — cards carry the creator's frame too. --}}
                {{-- v1.7.5 (R2): no geometry from the caller. The old
                     `group-hover/creator:bg-saffron` tinted the avatar BOX on
                     card hover; the composite box paints no background, and
                     the handle beside it already carries the hover affordance. --}}
                <x-user-avatar :user="$prompt->creator" size="md" :frame="$prompt->creator->activeFrame"/>
                <span class="min-w-0">
                    <span class="flex items-center gap-1 text-sm transition group-hover/creator:underline decoration-saffron decoration-2 underline-offset-2">
                        <x-user-handle :user="$prompt->creator" size="text-sm"/> <x-verified-badge :user="$prompt->creator" size="xs"/>
                    </span>
                    {{-- WCAG 2.1 AA: the timestamp is real information at
                         11px, so it clears 4.5:1 on the white card
                         (ink/60 ≈ 4.7:1; ink/40 ≈ 2.6:1 would not). --}}
                    <span class="block font-mono text-[11px] text-ink/60">{{ $prompt->updated_at->diffForHumans() }}</span>
                </span>
            </a>
        @else
            <div class="flex items-center gap-2.5">
                <span class="flex size-8 items-center justify-center rounded-full bg-ink text-[10px] font-bold text-saffron">?</span>
                <span class="text-sm text-ink/60">Unknown</span>
            </div>
        @endif
    </div>

    {{-- P1 (v1.9.1): newsfeed surfaces pass `large` so the post's artwork
         gets the 16:9 feed crop (and the bigger generated-banner type);
         every other caller keeps the 16:10 card cover unchanged. --}}
    <a href="{{ route('prompts.show', $prompt) }}" class="focus:outline-none" aria-label="View {{ $prompt->title }}">
        <x-prompt-cover :prompt="$prompt" :large="$large"/>
    </a>

    <div class="flex flex-1 flex-col gap-3 p-5 pt-4">
        <h3 class="font-semibold leading-snug text-ink">
            <a href="{{ route('prompts.show', $prompt) }}" class="transition group-hover:text-saffron-deep">
                {{ $prompt->title }}
            </a>
        </h3>

        <p class="line-clamp-2 text-sm leading-relaxed text-ink/60">{{ $prompt->description }}</p>

        {{-- S4: tighter chip rhythm — one gap step down from the old row. --}}
        <div class="flex flex-wrap items-center gap-1.5">
            @if ($prompt->category)
                <x-category-badge :category="$prompt->category"/>
            @endif
            @foreach ($tags as $tag)
                <span class="rounded-md border border-ink/10 bg-paper-deep px-2 py-0.5 font-mono text-[11px] text-ink/60">{{ $tag }}</span>
            @endforeach
        </div>

        {{-- Footer — the buy signal sits bottom-right. --}}
        <div class="mt-auto flex items-center justify-between gap-3 border-t border-ink/10 pt-3">
            <div class="flex items-center gap-1 font-mono text-xs text-ink/50" title="Community rating coming soon">
                <svg class="size-3.5 text-saffron-deep" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z" clip-rule="evenodd"/>
                </svg>
                <span>New</span>
            </div>
            @if ($prompt->isFree())
                <span class="shrink-0 rounded-full bg-emerald-100 px-2.5 py-1 font-mono text-xs font-bold text-emerald-800">Free</span>
            @else
                <span class="shrink-0 rounded-full bg-saffron/25 px-2.5 py-1 font-mono text-xs font-bold text-ink">
                    <x-sikka :amount="$prompt->price_sikka"/>
                </span>
            @endif
        </div>
    </div>

    {{-- T13 (v1.5.0) / P1 (v1.7.1): bookmark heart — Alpine optimistic
         flip, no reload. Saved = FILLED ROSE (text-rose-600 fill-current,
         aria-pressed true); unsaved = outline ink/40. Guests get a plain
         link to login; $saved pre-flips the state server-side. --}}
    @auth
        <button type="button"
                x-data="bookmarkHeart({{ Js::from(['url' => route('bookmarks.toggle', $prompt), 'saved' => $saved]) }})"
                @click="toggle()"
                {{-- H4: the saved state is SERVER-rendered, not only Alpine.
                     aria-pressed is static AND bound: static so the served
                     HTML (and a test, and a no-JS reader) is truthful,
                     bound so a live flip still updates it. --}}
                aria-pressed="{{ $saved ? 'true' : 'false' }}"
                :aria-pressed="saved"
                aria-label="Save this prompt"
                class="absolute right-4 top-4 z-10 flex size-9 items-center justify-center rounded-full bg-white/90 shadow-sm transition hover:scale-105 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-saffron">
            {{-- H4 (b) COLLISION-FREE FLIP: two mutually exclusive icons,
                 never two toggled utility pairs on one element. A static
                 SSR pair plus an Alpine :class pair on the SAME element
                 leaves BOTH pairs in the class attribute after a tap, and
                 the winner is decided by Tailwind's stylesheet order, not
                 by the author — observed in the browser: the heart turned
                 rose but stayed an OUTLINE (fill-none beat fill-current).
                 x-cloak on the server-inactive icon keeps the pre-Alpine
                 paint truthful (and correct with JS off entirely). --}}
            <svg class="size-5 text-rose-600 fill-current transition" @if (! $saved) x-cloak @endif
                 x-show="saved"
                 xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z"/>
            </svg>
            <svg class="size-5 text-ink/40 fill-none transition" @if ($saved) x-cloak @endif
                 x-show="! saved"
                 xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z"/>
            </svg>

            {{-- H4: a failed save must be VISIBLE. The revert alone
                 looks like the tap never registered. --}}
            <span x-show="toast" x-cloak x-text="toast" role="status"
                  class="pointer-events-none absolute right-0 top-11 z-20 w-max max-w-[14rem] rounded-full bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white shadow-lg"></span>
        </button>
    @endauth
</article>
