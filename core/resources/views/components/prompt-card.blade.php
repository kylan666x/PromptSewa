@props(['prompt', 'saved' => null])

@php
    /** @var \App\Models\Prompt $prompt */
    // H4 (v1.7.4) PRE-FLIP PARITY: the card asks the per-request
    // bookmarked-id set instead of defaulting to false. Passing :saved
    // explicitly still wins (a caller that knows better may override),
    // but no surface can silently ship an unsaved heart again.
    // NOTE: a Blade {{-- --}} comment inside @php is a parse error — use
    // PHP comments in this block.
    $saved = $saved ?? \App\Support\BookmarkedIds::contains($prompt);

    // F3 (v1.9.2) — the feed replicates TWO social post shapes:
    //   * IMAGE/VIDEO listings   -> the Instagram post (artwork first, caption
    //     under it, edge-to-edge art on phones);
    //   * everything else        -> the Facebook text post (no artwork, the
    //     copy IS the post).
    // Sikka is the ONLY currency on buyer surfaces (S1, v1.9.0): the price
    // chip is the single buy signal and carries no NPR mirror.
    $visual = in_array($prompt->type, [\App\Models\Prompt::TYPE_IMAGE, \App\Models\Prompt::TYPE_VIDEO], true);

    // "No artificial truncation unless it exceeds a high limit, then See more"
    // — the founder's F3 body contract. The split is server-side so the post
    // is readable with JS off (the toggle simply reveals what is already
    // there); no fabricated summary text is ever generated.
    $caption = trim((string) $prompt->description);
    $limit = 500;
    $truncated = mb_strlen($caption) > $limit;
    $visibleText = $truncated ? rtrim(mb_substr($caption, 0, $limit)) : $caption;
    $restText = $truncated ? trim(mb_substr($caption, $limit)) : '';
@endphp

{{-- F3 (v1.9.2) SOCIAL FEED CARD.

     G1 (v1.7.4) INVARIANT: NO overflow-hidden on the card root — a creator's
     frame ornament protrudes past the avatar box and a clipping root would eat
     it. The Instagram post still reads edge-to-edge because the ARTWORK clips
     itself (x-prompt-cover) and rounds its own top corners; nothing else needs
     the root clip.

     Reading order (the social mental model, locked by SocialFeedReplicaTest):
       header (identity)  ->  artwork (visual posts only)  ->  caption  ->
       price chip  ->  action bar (Like · Comment | Share · Save). --}}
<article class="group relative flex flex-col rounded-xl border border-ink/5 bg-paper shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-card-hover">
    {{-- Header — "who posted this". --}}
    <div class="flex items-center gap-3 {{ $visual ? 'p-3' : 'p-4' }}">
        @if ($prompt->creator)
            <a href="{{ route('creators.show', $prompt->creator) }}"
               class="group/creator flex min-w-0 items-center gap-3 rounded-full transition focus:outline-none focus-visible:ring-2 focus-visible:ring-saffron-deep"
               aria-label="View profile of {{ $prompt->creator->name }}">
                {{-- W1 (v1.7.3): frame parity — cards carry the creator's frame too. --}}
                <x-user-avatar :user="$prompt->creator" size="sm" :frame="$prompt->creator->activeFrame"/>
                <span class="min-w-0">
                    <span class="flex items-center gap-1.5">
                        <span class="truncate text-sm font-semibold text-ink transition group-hover/creator:underline decoration-saffron decoration-2 underline-offset-2">{{ $prompt->creator->name }}</span>
                        <x-verified-badge :user="$prompt->creator" size="xs"/>
                        {{-- WCAG 2.1 AA: 4.5:1 floor for small text — ink/60. --}}
                        <span class="shrink-0 text-sm text-ink/60" aria-hidden="true">&middot;</span>
                        <span class="shrink-0 text-sm text-ink/60">{{ $prompt->updated_at->diffForHumans() }}</span>
                    </span>
                    <x-user-handle :user="$prompt->creator" size="text-[11px]"/>
                </span>
            </a>
        @else
            <div class="flex items-center gap-3">
                <span class="flex size-8 items-center justify-center rounded-full bg-ink text-[10px] font-bold text-saffron">?</span>
                <span class="text-sm text-ink/60">Unknown</span>
            </div>
        @endif
    </div>

    @if ($visual)
        {{-- Instagram: edge-to-edge artwork, square on phones / 4:5 from md. --}}
        <a href="{{ route('prompts.show', $prompt) }}" class="focus:outline-none" aria-label="View {{ $prompt->title }}">
            <x-prompt-cover :prompt="$prompt" feed class="rounded-t-xl"/>
        </a>

        <div class="p-4">
            <p class="text-base leading-relaxed text-ink">
                <span class="font-semibold">{{ $prompt->creator?->name }}</span>
                <a href="{{ route('prompts.show', $prompt) }}"
                   class="font-semibold underline decoration-saffron decoration-2 underline-offset-2 transition hover:text-saffron-deep">{{ $prompt->title }}</a>
            </p>

            @if ($caption !== '')
                <p class="mt-1 whitespace-pre-wrap text-base leading-relaxed text-ink" @if ($truncated) x-data="{ expanded: false }" @endif>
                    <span>{{ $visibleText }}@if ($truncated && ! $restText)…@endif</span>
                    @if ($truncated)
                        <span x-show="expanded" x-cloak>{{ $restText }}</span>
                        <button type="button" @click="expanded = true" x-show="! expanded"
                                class="mt-1 block text-sm font-semibold text-ink/70 underline decoration-saffron decoration-2 underline-offset-2 transition hover:text-ink">
                            See more
                        </button>
                    @endif
                </p>
            @endif
        </div>
    @else
        {{-- Facebook: the copy IS the post. --}}
        <div class="px-4 pb-3" @if ($truncated) x-data="{ expanded: false }" @endif>
            <h3 class="font-semibold leading-snug text-ink">
                <a href="{{ route('prompts.show', $prompt) }}" class="transition group-hover:text-saffron-deep">{{ $prompt->title }}</a>
            </h3>

            @if ($caption !== '')
                <p class="mt-1.5 whitespace-pre-wrap text-base leading-relaxed text-ink">
                    <span>{{ $visibleText }}@if ($truncated && ! $restText)…@endif</span>
                    @if ($truncated)
                        <span x-show="expanded" x-cloak>{{ $restText }}</span>
                        <button type="button" @click="expanded = true" x-show="! expanded"
                                class="mt-1 block text-sm font-semibold text-ink/70 underline decoration-saffron decoration-2 underline-offset-2 transition hover:text-ink">
                            See more
                        </button>
                    @endif
                </p>
            @endif
        </div>
    @endif

    {{-- The one buy signal — Sikka only, never an NPR mirror. --}}
    <div class="mt-auto flex items-center justify-end px-4 pb-3">
        @if ($prompt->isFree())
            <span class="shrink-0 rounded-full bg-emerald-100 px-2.5 py-1 font-mono text-xs font-bold text-emerald-800">Free</span>
        @else
            <span class="shrink-0 rounded-full bg-saffron/25 px-2.5 py-1 font-mono text-xs font-bold text-ink">
                <x-sikka :amount="$prompt->price_sikka"/>
            </span>
        @endif
    </div>

    {{-- Action bar — the founder's F3 footer contract. --}}
    <div class="flex items-center justify-between border-t border-ink/5 px-4 py-2 text-sm font-medium text-ink/60">
        <div class="flex items-center gap-4">
            {{-- Like / Comment: honest stubs — there is no likes or comments
                 backend yet, so these are NOT dead buttons. They carry the
                 social affordance visibly and say what they are on hover
                 (DESIGN.md: unbuilt features get honest wording, never a
                 fabricated claim). --}}
            <span class="inline-flex items-center gap-1.5" title="Likes are coming soon">
                <svg class="size-4 text-ink/40" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.633 10.25c.806 0 1.533-.446 2.031-1.08a9.041 9.041 0 0 1 2.861-2.4c.723-.384 1.35-.956 1.653-1.715a4.498 4.498 0 0 0 .322-1.672V3a.75.75 0 0 1 .75-.75 2.25 2.25 0 0 1 2.25 2.25c0 1.152-.26 2.243-.723 3.218-.266.558.107 1.282.725 1.282m0 0h3.126c1.026 0 1.945.694 2.054 1.715.045.422.068.85.068 1.285a11.95 11.95 0 0 1-2.649 7.521c-.388.482-.987.729-1.605.729H14.23c-.483 0-.964-.078-1.423-.23l-3.114-1.04a4.501 4.501 0 0 0-1.423-.23H5.904m10.598-9.75H14.25M5.904 18.5c.083.205.173.405.27.602.197.4-.078.898-.523.898h-.908c-.889 0-1.713-.518-1.972-1.368a12 12 0 0 1-.521-3.507c0-1.553.295-3.036.831-4.398C3.387 9.953 4.167 9.5 5 9.5h1.053c.472 0 .745.556.5.96a8.958 8.958 0 0 0-1.302 4.665c0 1.194.232 2.333.654 3.375Z"/>
                </svg>
                Like
            </span>
            <span class="inline-flex items-center gap-1.5" title="Comments are coming soon">
                <svg class="size-4 text-ink/40" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75h6.75m-6.75 3.75h4.5M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z"/>
                </svg>
                Comment
            </span>
        </div>

        <div class="flex items-center gap-4">
            {{-- Share: real — native Web Share API, clipboard fallback. --}}
            <span class="relative inline-flex items-center">
                <button type="button"
                        x-data="sharePost({{ Js::from(['url' => route('prompts.show', $prompt), 'title' => $prompt->title]) }})"
                        @click="share()"
                        class="inline-flex items-center gap-1.5 transition hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-saffron"
                        aria-label="Share {{ $prompt->title }}">
                    <svg class="size-4 text-ink/40" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z"/>
                    </svg>
                    Share
                </button>
                <span x-show="notice" x-cloak x-text="notice" role="status"
                      class="pointer-events-none absolute bottom-full right-0 mb-2 w-max max-w-[16rem] rounded-full bg-ink px-3 py-1.5 text-xs font-semibold text-paper shadow-lg"></span>
            </span>

            {{-- Save: the bookmark heart, same Alpine component and the same
                 two mutually exclusive icons as before (H4 contract). --}}
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
                        class="relative inline-flex items-center gap-1.5 transition hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-saffron">
                    {{-- H4 (b) COLLISION-FREE FLIP: two mutually exclusive icons,
                         never two toggled utility pairs on one element. --}}
                    <svg class="size-4 text-rose-600 fill-current" @if (! $saved) x-cloak @endif
                         x-show="saved"
                         xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z"/>
                    </svg>
                    <svg class="size-4 text-ink/40 fill-none" @if ($saved) x-cloak @endif
                         x-show="! saved"
                         xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z"/>
                    </svg>
                    Save

                    {{-- H4: a failed save must be VISIBLE. The revert alone
                         looks like the tap never registered. --}}
                    <span x-show="toast" x-cloak x-text="toast" role="status"
                          class="pointer-events-none absolute bottom-full right-0 mb-2 z-20 w-max max-w-[16rem] rounded-full bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white shadow-lg"></span>
                </button>
            @else
                {{-- Guests: no heart (and no bookmarkHeart scope) — an honest
                     door to sign in instead. --}}
                <a href="{{ route('login') }}"
                   class="inline-flex items-center gap-1.5 transition hover:text-ink">
                    <svg class="size-4 text-ink/40" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z"/>
                    </svg>
                    Save
                </a>
            @endauth
        </div>
    </div>
</article>
