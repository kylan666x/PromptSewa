@props(['prompt'])

@php
    /** @var \App\Models\Prompt $prompt */
    $tags = collect($prompt->latestVersion?->tags ?? [])->take(3);
@endphp

<article class="group flex flex-col overflow-hidden rounded-2xl border border-ink/10 bg-white shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-saffron-deep hover:shadow-card-hover">
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
                    <span class="flex size-6 items-center justify-center rounded-full bg-ink text-[10px] font-bold text-saffron transition group-hover/creator:bg-saffron group-hover/creator:text-ink">
                        {{ mb_substr($prompt->creator->name, 0, 1) }}
                    </span>
                    <span class="text-xs text-ink/60 transition group-hover/creator:text-ink group-hover/creator:underline decoration-saffron decoration-2 underline-offset-2">{{ $prompt->creator->name }}</span>
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
