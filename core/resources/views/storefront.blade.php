<x-app-layout>
    <x-seo :title="''" :description="$siteTagline ?? null"/>
    {{-- Hero — warm paper, saffron pill CTAs, mono eyebrow chips --}}
    <section class="relative overflow-hidden">
        <div class="absolute inset-0 -z-10 bg-[radial-gradient(60rem_30rem_at_50%_-10rem,rgba(245,197,24,0.18),transparent)]"></div>
        <div class="mx-auto max-w-7xl px-4 pb-16 pt-14 text-center sm:px-6 md:pt-20">
            <span class="inline-flex items-center gap-2 rounded-full border border-ink/15 bg-white px-3 py-1 font-mono text-xs font-medium text-ink/70 shadow-sm">
                <span class="relative flex size-2">
                    <span class="absolute inline-flex size-full animate-ping rounded-full bg-saffron-deep opacity-60"></span>
                    <span class="relative inline-flex size-2 rounded-full bg-saffron-deep"></span>
                </span>
                New prompts added weekly
            </span>

            {{-- G3 (v1.7.4): the hero type scale steps DOWN below md
                 (text-3xl -> sm:text-5xl -> md:text-6xl) so the h1 never
                 wraps into four lines on a 360px phone. --}}
            <h1 class="mx-auto mt-5 max-w-3xl text-3xl font-bold tracking-tight text-ink sm:mt-6 sm:text-5xl md:text-6xl">
                <em class="font-bold italic">Version control</em> for your AI prompts
            </h1>
            <p class="mx-auto mt-4 max-w-2xl text-base leading-relaxed text-ink/60 sm:mt-5 sm:text-lg">
                Discover, test, buy and sell battle-tested prompts — with git-like
                history, forking and licensing built in.
            </p>

            {{-- ONE primary CTA on mobile; the second action demotes to a
                 text link so the fold stays a single decision. --}}
            <div class="mt-7 flex flex-col items-center gap-3 md:mt-8 md:flex-row md:flex-wrap md:justify-center">
                <a href="{{ route('register') }}" class="w-full rounded-full bg-saffron px-6 py-3 text-sm font-bold text-ink shadow-card transition hover:-translate-y-0.5 hover:bg-saffron-deep hover:shadow-card-hover md:w-auto">
                    Unlock the library
                </a>
                <a href="{{ route('library.index') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-ink/70 underline decoration-saffron decoration-2 underline-offset-4 transition hover:text-saffron-deep md:rounded-full md:border md:border-ink/20 md:bg-white md:px-6 md:py-3 md:shadow-sm md:hover:-translate-y-0.5 md:hover:border-ink/40 md:hover:shadow-card">
                    Explore prompts
                    <span class="md:hidden" aria-hidden="true">&rarr;</span>
                </a>
            </div>

            {{-- Prominent search bar --}}
            <form action="{{ route('library.index') }}" method="GET" role="search" class="mx-auto mt-7 max-w-xl sm:mt-9">
                <div class="flex items-center gap-2 rounded-full border border-ink/15 bg-white p-2 pl-4 shadow-card focus-within:border-saffron-deep focus-within:ring-2 focus-within:ring-saffron/40 sm:pl-5">
                    <svg class="size-5 shrink-0 text-creak" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                    </svg>
                    <input
                        type="search"
                        name="q"
                        maxlength="80"
                        placeholder="Search prompts, e.g. cold email sequence…"
                        class="w-full bg-transparent py-2 text-sm text-ink placeholder-creak outline-none"
                    >
                    <x-button type="submit">Search</x-button>
                </div>
            </form>

            {{-- G3: on phones the trending chips become a horizontal
                 scroll-snap RAIL (CSS only — no JS, no dependency). The
                 rail is focusable for keyboard users so WCAG focus order
                 still reaches every chip. --}}
            <div class="mt-6 md:flex md:flex-wrap md:items-center md:justify-center md:gap-2">
                <p class="sr-only md:not-sr-only md:text-sm md:text-ink/50">Trending:</p>
                <div class="-mx-4 flex snap-x snap-mandatory gap-2 overflow-x-auto px-4 pb-2 md:mx-0 md:flex-wrap md:justify-center md:overflow-visible md:px-0 md:pb-0"
                     role="group" aria-label="Trending searches" tabindex="0">
                    @foreach (['cold email', 'midjourney', 'code review', 'blog intro'] as $term)
                        <a href="{{ route('library.index', ['q' => $term]) }}"
                           class="shrink-0 snap-start rounded-full border border-ink/15 bg-white/60 px-3 py-1 font-mono text-xs transition hover:border-saffron-deep hover:bg-white hover:text-ink">{{ $term }}</a>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- Library stats — G3: on phones these are three inline MONO CHIPS
         in a snap rail (the 3-column strip squeezed 3 numerals into 120px);
         from md up it stays the original inkwell strip, unchanged. --}}
    <section class="mx-auto max-w-7xl px-4 sm:px-6">
        <div class="-mx-4 flex snap-x snap-mandatory gap-3 overflow-x-auto px-4 md:mx-0 md:grid md:grid-cols-3 md:gap-4 md:overflow-visible md:px-0">
            <div class="min-w-[10rem] shrink-0 snap-start rounded-2xl bg-ink px-4 py-3 text-center shadow-card md:rounded-3xl md:p-6">
                <p class="font-mono text-xl font-bold text-paper md:text-2xl">{{ number_format($stats['prompts']) }}+</p>
                <p class="mt-1 text-xs text-paper/50">Published prompts</p>
            </div>
            <div class="min-w-[10rem] shrink-0 snap-start rounded-2xl bg-ink px-4 py-3 text-center shadow-card md:rounded-3xl md:p-6">
                <p class="font-mono text-xl font-bold text-paper md:text-2xl">{{ number_format($stats['creators']) }}</p>
                <p class="mt-1 text-xs text-paper/50">Creators</p>
            </div>
            <div class="min-w-[10rem] shrink-0 snap-start rounded-2xl bg-ink px-4 py-3 text-center shadow-card md:rounded-3xl md:p-6">
                <p class="font-mono text-xl font-bold text-saffron md:text-2xl">{{ number_format($stats['free']) }}</p>
                <p class="mt-1 text-xs text-paper/50">Free prompts</p>
            </div>
        </div>
    </section>

    {{-- Featured prompts --}}
    <section class="mx-auto max-w-7xl px-4 pt-16 sm:px-6">
        <div class="flex items-end justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-ink">Fresh from the library</h2>
                <p class="mt-1 text-sm text-ink/60">Hand-reviewed prompts, ready to copy and run.</p>
            </div>
            <a href="{{ route('library.index') }}" class="ml-auto shrink-0 self-end text-sm font-semibold text-ink underline decoration-saffron decoration-2 underline-offset-4 transition hover:text-saffron-deep">
                Browse all &rarr;
            </a>
        </div>

        @if ($featured->isEmpty())
            <div class="mt-8">
                <x-empty-state
                    title="The library is warming up"
                    description="No prompts have been published yet. Check back soon — creators are hard at work."
                />
            </div>
        @else
            {{-- G3: below md this is a scroll-snap CAROUSEL (one card per
                 screen-ish); from md up it is the original grid. Pure CSS —
                 the cards are the same component, so nothing is duplicated
                 in the DOM. --}}
            <div class="-mx-4 mt-8 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-4 sm:-mx-6 sm:px-6 md:mx-0 md:grid md:grid-cols-2 md:gap-6 md:overflow-visible md:px-0 md:pb-0 lg:grid-cols-3">
                @foreach ($featured as $prompt)
                    <div class="w-[82%] shrink-0 snap-start sm:w-[60%] md:w-auto">
                        <x-prompt-card :prompt="$prompt"/>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Image prompt gallery — PromptPlum-style visual wall --}}
    @if ($imagePrompts->isNotEmpty())
        <section class="mx-auto max-w-7xl px-4 pt-16 sm:px-6">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-ink">Image prompt gallery</h2>
                    <p class="mt-1 text-sm text-ink/60">See the result first — the prompt is right on the card.</p>
                </div>
                <a href="{{ route('library.index', ['type' => 'image']) }}" class="ml-auto shrink-0 self-end text-sm font-semibold text-ink underline decoration-saffron decoration-2 underline-offset-4 transition hover:text-saffron-deep">
                    Browse all image prompts &rarr;
                </a>
            </div>

            {{-- G3: 2-col with 4:5 covers on phones; the desktop rhythm is
                 unchanged (sm:grid-cols-3 lg:grid-cols-4). --}}
            <div class="mt-8 grid grid-cols-2 gap-3 sm:gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($imagePrompts as $prompt)
                    <x-image-prompt-card :prompt="$prompt"/>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Categories — paper tiles --}}
    @if ($categories->isNotEmpty())
        <section class="mx-auto max-w-7xl px-4 pt-16 sm:px-6">
            <h2 class="text-2xl font-bold tracking-tight text-ink">Browse by category</h2>
            <div class="mt-6 -mx-4 flex snap-x snap-mandatory gap-3 overflow-x-auto px-4 pb-2 sm:mx-0 sm:grid sm:grid-cols-3 sm:overflow-visible sm:px-0 sm:pb-0 lg:grid-cols-5">
                @foreach ($categories as $category)
                    <a href="{{ route('library.category', $category) }}"
                       class="group min-w-[9rem] shrink-0 snap-start rounded-2xl border border-ink/10 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-saffron-deep hover:shadow-card-hover sm:min-w-0">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-paper-deep text-xl">
                            {{ $category->icon ?? '📁' }}
                        </div>
                        <p class="mt-3 font-semibold text-ink transition group-hover:text-saffron-deep">{{ $category->name }}</p>
                        <p class="mt-0.5 font-mono text-xs text-ink/50">{{ $category->prompts_count }} {{ Str::plural('prompt', $category->prompts_count) }}</p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Creator CTA — inkwell panel, saffron pill --}}
    <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6">
        <div class="relative overflow-hidden rounded-3xl bg-ink p-10 text-center shadow-card-hover sm:p-14">
            <div class="absolute inset-0 opacity-40 [background-image:radial-gradient(circle_at_1px_1px,rgba(250,250,247,0.14)_1px,transparent_0)] [background-size:16px_16px]"></div>
            <div class="relative">
                <h2 class="text-3xl font-bold tracking-tight text-paper">Turn your prompt library into <em class="italic text-saffron">income</em></h2>
                <p class="mx-auto mt-3 max-w-xl text-paper/60">
                    Publish your best prompts, set your price in NPR, and earn from every
                    copy — with version control your buyers can trust.
                </p>
                <div class="mt-8 flex justify-center">
                    <a href="{{ route('register') }}" class="rounded-full bg-saffron px-6 py-3 text-sm font-bold text-ink shadow-lg transition hover:-translate-y-0.5 hover:bg-saffron-deep">Become a creator</a>
                </div>
            </div>
        </div>
    </section>
</x-app-layout>
