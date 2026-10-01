@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $events */
    /** @var int $page */
@endphp

<x-app-layout>
    <x-seo title="Community feed" description="The latest publishes, sales milestones, badges and packs from the PromptSewa community." :canonical="route('feed.index')" robots="noindex, follow"/>

    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <header class="text-center">
            <p class="font-mono text-xs font-semibold uppercase tracking-[0.2em] text-saffron-deep">Community pulse</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-ink">What's happening</h1>
            <p class="mt-2 text-sm text-ink/60">Fresh publishes, real sales milestones, earned badges — straight from the ledger of activity.</p>
        </header>

        <ol class="mt-8 space-y-3">
            @forelse ($events as $event)
                <li class="rounded-2xl border border-ink/10 bg-white p-5 shadow-sm">
                    <div class="flex items-start gap-3">
                        {{-- G4: actor identity = avatar + badge + frame + XP chip --}}
                        <a href="{{ route('creators.show', $event->actor) }}" class="shrink-0">
                            <x-user-avatar :user="$event->actor" size="md" :frame="$event->actor->activeFrame"/>
                        </a>
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-sm text-ink/80">
                                <a href="{{ route('creators.show', $event->actor) }}" class="font-semibold text-ink hover:text-saffron-deep">{{ $event->actor->name }}</a>
                                <x-verified-badge :user="$event->actor" size="xs"/>
                                <span class="rounded-full bg-paper-deep px-2 py-0.5 font-mono text-[10px] font-bold text-ink/60">Lv {{ \App\Services\GamificationService::levelForXp((int) $event->actor->xp) }}</span>

                                @if ($event->type === \App\Models\FeedEvent::TYPE_PROMPT_PUBLISHED)
                                    <span>published</span>
                                    <a href="{{ route('prompts.show', $event->subject?->slug ?? '#') }}" class="font-semibold text-ink hover:text-saffron-deep">{{ $event->meta['title'] ?? 'a prompt' }}</a>
                                @elseif ($event->type === \App\Models\FeedEvent::TYPE_SALE_MILESTONE)
                                    <span>hit</span>
                                    <span class="font-semibold text-ink">{{ number_format($event->meta['sales_count']) }} sales</span>
                                    <span>with</span>
                                    <a href="{{ route('prompts.show', $event->subject?->slug ?? '#') }}" class="font-semibold text-ink hover:text-saffron-deep">{{ $event->meta['title'] ?? 'a prompt' }}</a>
                                @elseif ($event->type === \App\Models\FeedEvent::TYPE_BADGE_EARNED)
                                    <span>earned the</span>
                                    <span class="font-semibold text-ink">{{ $event->meta['badge_name'] ?? 'a badge' }}</span>
                                    <span>badge</span>
                                @elseif ($event->type === \App\Models\FeedEvent::TYPE_PACK_CREATED)
                                    <span>launched the pack</span>
                                    <a href="{{ route('packs.show', $event->subject?->slug ?? '#') }}" class="font-semibold text-ink hover:text-saffron-deep">{{ $event->meta['name'] ?? '' }}</a>
                                @endif
                            </p>
                            <p class="mt-1 font-mono text-[11px] text-ink/40">{{ $event->created_at->diffForHumans() }}</p>
                        </div>
                        {{-- Badge art (alpha PNG) --}}
                        @if ($event->type === \App\Models\FeedEvent::TYPE_BADGE_EARNED && $event->meta['badge_image'] ?? null)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($event->meta['badge_image']) }}" alt=""
                                 class="size-10 shrink-0" loading="lazy">
                        @endif
                    </div>
                </li>
            @empty
                <li class="rounded-2xl border border-dashed border-ink/20 bg-white p-10 text-center">
                    <p class="text-sm text-ink/60">The feed is quiet — publish a prompt to start the pulse.</p>
                </li>
            @endforelse
        </ol>

        @if ($events->hasPages())
            <div class="mt-6">{{ $events->links('pagination::tailwind') }}</div>
        @endif
    </div>
</x-app-layout>
