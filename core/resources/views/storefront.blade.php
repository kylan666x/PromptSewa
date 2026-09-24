<x-app-layout>
    {{-- Hero — warm paper, saffron pill CTAs, mono eyebrow chips --}}
    <section class="relative overflow-hidden">
        <div class="absolute inset-0 -z-10 bg-[radial-gradient(60rem_30rem_at_50%_-10rem,rgba(245,197,24,0.18),transparent)]"></div>
        <div class="mx-auto max-w-7xl px-4 pb-16 pt-20 text-center sm:px-6">
            <span class="inline-flex items-center gap-2 rounded-full border border-ink/15 bg-white px-3 py-1 font-mono text-xs font-medium text-ink/70 shadow-sm">
                <span class="relative flex size-2">
                    <span class="absolute inline-flex size-full animate-ping rounded-full bg-saffron-deep opacity-60"></span>
                    <span class="relative inline-flex size-2 rounded-full bg-saffron-deep"></span>
                </span>
                New prompts added weekly
            </span>

            <h1 class="mx-auto mt-6 max-w-3xl text-4xl font-bold tracking-tight text-ink sm:text-6xl">
                <em class="font-bold italic">Version control</em> for your AI prompts
            </h1>
            <p class="mx-auto mt-5 max-w-2xl text-lg leading-relaxed text-ink/60">
                Discover, test, buy and sell battle-tested prompts — with git-like
                history, forking and licensing built in.
            </p>

            {{-- Primary actions — saffron pills, GoP-style pair --}}
            <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('register') }}" class="rounded-full bg-saffron px-6 py-3 text-sm font-bold text-ink shadow-card transition hover:-translate-y-0.5 hover:bg-saffron-deep hover:shadow-card-hover">
                    Unlock the library
                </a>
                <a href="{{ route('library.index') }}" class="rounded-full border border-ink/20 bg-white px-6 py-3 text-sm font-bold text-ink shadow-sm transition hover:-translate-y-0.5 hover:border-ink/40 hover:shadow-card">
                    Explore prompts
                </a>
            </div>

            {{-- Prominent search bar --}}
            <form action="{{ route('library.index') }}" method="GET" role="search" class="mx-auto mt-9 max-w-xl">
                <div class="flex items-center gap-2 rounded-full border border-ink/15 bg-white p-2 pl-5 shadow-card focus-within:border-saffron-deep focus-within:ring-2 focus-within:ring-saffron/40">
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

            <div class="mt-6 flex flex-wrap items-center justify-center gap-2 text-sm text-ink/50">
                <span>Trending:</span>
                @foreach (['cold email', 'midjourney', 'code review', 'blog intro'] as $term)
                    <a href="{{ route('library.index', ['q' => $term]) }}" class="rounded-full border border-ink/15 bg-white/60 px-3 py-1 font-mono text-xs transition hover:border-saffron-deep hover:bg-white hover:text-ink">{{ $term }}</a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Library stats — inkwell strip with mono numerals --}}
    <section class="mx-auto max-w-7xl px-4 sm:px-6">
        <div class="grid grid-cols-3 gap-4 rounded-3xl bg-ink p-6 text-center shadow-card-hover">
            <div>
                <p class="font-mono text-2xl font-bold text-paper">{{ number_format($stats['prompts']) }}+</p>
                <p class="mt-1 text-xs text-paper/50">Published prompts</p>
            </div>
            <div>
                <p class="font-mono text-2xl font-bold text-paper">{{ number_format($stats['creators']) }}</p>
                <p class="mt-1 text-xs text-paper/50">Creators</p>
            </div>
            <div>
                <p class="font-mono text-2xl font-bold text-saffron">{{ number_format($stats['free']) }}</p>
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
            <a href="{{ route('library.index') }}" class="shrink-0 text-sm font-semibold text-ink underline decoration-saffron decoration-2 underline-offset-4 transition hover:text-saffron-deep">
                Browse all →
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
            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($featured as $prompt)
                    <x-prompt-card :prompt="$prompt"/>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Categories — paper tiles --}}
    @if ($categories->isNotEmpty())
        <section class="mx-auto max-w-7xl px-4 pt-16 sm:px-6">
            <h2 class="text-2xl font-bold tracking-tight text-ink">Browse by category</h2>
            <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ($categories as $category)
                    <a href="{{ route('library.category', $category) }}"
                       class="group rounded-2xl border border-ink/10 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-saffron-deep hover:shadow-card-hover">
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
