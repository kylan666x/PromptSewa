<x-app-layout>
    <x-seo :title="$category?->name ?? 'Prompt library'" :description="$category?->name ? 'Browse '.$category->name.' AI prompts on PromptSewa.' : 'Browse premium AI prompts — text, image, video, agentic and skill listings.'"/>
    @php
        $heading = $category ? $category->name : ($q !== '' ? 'Search results' : 'Prompt Library');
        $subheading = $category
            ? 'Every published prompt in '.$category->name.'.'
            : ($q !== '' ? 'Prompts matching “'.$q.'”.' : 'Hand-reviewed prompts, ready to copy and run.');
    @endphp

    {{-- S4 (v1.9.0): a touch more breathing room above the feed, and a
         larger heading — the catalog now opens like a feed, not a table. --}}
    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6">
        <header class="flex flex-col gap-1">
            <h1 class="text-3xl font-bold tracking-tight text-ink sm:text-4xl">{{ $heading }}</h1>
            <p class="mt-1 text-sm text-ink/60">{{ $subheading }}</p>
        </header>

        {{-- T12 (v1.5.0): horizontal category chip row — all breakpoints. The
             burger drawer is retired; this row is the mobile category nav. --}}
        <nav aria-label="Category shortcuts" class="mt-5 -mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
            <div class="flex w-max gap-1.5 pb-1">
                <a href="{{ route('library.index', $q !== '' ? ['q' => $q] : []) }}"
                   class="shrink-0 rounded-full border px-3.5 py-1.5 text-xs font-medium transition {{ $category === null ? 'border-saffron-deep bg-saffron/25 text-ink' : 'border-ink/10 bg-white text-ink/80 hover:border-saffron-deep hover:text-ink' }}">All</a>
                @foreach ($categories as $chip)
                    <a href="{{ route('library.category', $chip) }}"
                       class="shrink-0 rounded-full border px-3.5 py-1.5 text-xs font-medium transition {{ $category?->id === $chip->id ? 'border-saffron-deep bg-saffron/25 text-ink' : 'border-ink/10 bg-white text-ink/80 hover:border-saffron-deep hover:text-ink' }}">{{ $chip->name }}</a>
                @endforeach
            </div>
        </nav>

        <div class="mt-8 flex flex-col gap-6 lg:flex-row lg:gap-8">
            {{-- Category sidebar --}}
            <aside class="shrink-0 lg:w-56">
                <nav aria-label="Categories" class="rounded-2xl border border-ink/10 bg-white p-3 shadow-sm">
                    <p class="px-2 pb-2 font-mono text-xs font-semibold uppercase tracking-wider text-ink/50">Categories</p>
                    <ul class="flex flex-row gap-1 overflow-x-auto lg:flex-col lg:overflow-visible">
                        <li>
                            <a href="{{ route('library.index') }}"
                               class="flex items-center justify-between gap-2 whitespace-nowrap rounded-xl px-3 py-2 text-sm transition {{ $category === null ? 'bg-saffron/25 font-semibold text-ink' : 'text-ink/70 hover:bg-paper-deep hover:text-ink' }}">
                                All prompts
                            </a>
                        </li>
                        @foreach ($categories as $sidebarCategory)
                            <li>
                                <a href="{{ route('library.category', $sidebarCategory) }}"
                                   class="flex items-center justify-between gap-2 whitespace-nowrap rounded-xl px-3 py-2 text-sm transition {{ ($category?->is($sidebarCategory)) ? 'bg-saffron/25 font-semibold text-ink' : 'text-ink/70 hover:bg-paper-deep hover:text-ink' }}">
                                    <span>{{ $sidebarCategory->name }}</span>
                                    <span class="font-mono text-xs text-ink/40">{{ $sidebarCategory->prompts_count }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            </aside>

            {{-- Matching creators (search only) --}}
            @if (isset($creators) && $creators->isNotEmpty())
                <section class="mb-8" aria-label="Matching creators">
                    <h2 class="font-mono text-xs font-semibold uppercase tracking-widest text-ink/50">Creators</h2>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        @foreach ($creators as $creator)
                            {{-- S4: creators keep the composite-box avatar; only
                                 the card rhythm tightens. --}}
                            <a href="{{ route('creators.show', $creator) }}"
                               class="group flex items-center gap-3 rounded-2xl border border-ink/10 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-saffron-deep hover:shadow-card-hover">
                                <x-user-avatar :user="$creator" size="lg" :frame="$creator->activeFrame"/>
                                <span class="min-w-0 flex-1">
                                    <span class="flex items-center gap-1.5 font-semibold text-ink transition group-hover:text-saffron-deep">
                                        {{ $creator->name }} <x-user-handle :user="$creator" size="text-xs" class="font-normal opacity-70"/>
                                        <x-verified-badge :user="$creator" size="xs"/>
                                    </span>
                                    <span class="block font-mono text-xs text-ink/50">{{ $creator->prompts_count }} {{ \Illuminate\Support\Str::plural('prompt', $creator->prompts_count) }}</span>
                                </span>
                                <span class="text-ink/30 transition group-hover:text-saffron-deep">&rarr;</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Prompt grid --}}
            <div class="min-w-0 flex-1">
                @if ($prompts->isEmpty())
                    <x-empty-state
                        title="No prompts found"
                        description="{{ $q !== '' ? 'Nothing matched your search. Try a different keyword or browse by category.' : 'No prompts have been published in this category yet.' }}">
                        <x-slot:actions>
                            <x-button href="{{ route('library.index') }}" variant="secondary">Clear search</x-button>
                        </x-slot:actions>
                    </x-empty-state>
                @else
                    {{-- P1 (v1.9.1): the TRUE newsfeed — one full-width post per
                         row at EVERY breakpoint, centered on a max-w-3xl
                         column with gap-6 air between posts. The multi-column
                         grid is retired: no `sm:grid-cols-2`, no
                         `xl:grid-cols-3`, on any screen. --}}
                    <div class="mx-auto grid w-full max-w-3xl grid-cols-1 gap-6">
                        @foreach ($prompts as $prompt)
                            {{-- H4: the card resolves its own pre-flip from the per-request
                                 bookmarked-id set (one query per request,
                                 not one per card). --}}
                            {{-- F3 (v1.9.2): the card picks its own social
                                 shape — Instagram post for image/video
                                 listings, Facebook text post otherwise. --}}
                            <x-prompt-card :prompt="$prompt"/>
                        @endforeach
                    </div>

                    <div class="mt-8">
                        {{ $prompts->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
