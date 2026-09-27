@props([
    'categories',
    'siteName' => 'PromptSewa',
    'brandLogoPath' => '',
])

<nav class="sticky top-0 z-40 border-b border-ink/10 bg-paper/90 backdrop-blur" x-data="{ mobile: false, cats: false }">
    <div class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 sm:px-6">
        {{-- Logo (admin upload) or the wordmark badge --}}
        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2 text-lg font-semibold tracking-tight text-ink">
            @if ($brandLogoPath !== '')
                <img src="{{ asset('storage/'.$brandLogoPath) }}" alt="{{ $siteName }} logo" class="size-8 rounded-lg object-contain">
            @else
                <span class="flex size-8 items-center justify-center rounded-lg bg-saffron text-sm font-bold text-ink">{{ mb_substr($siteName, 0, 2) }}</span>
            @endif
            <span class="hidden sm:inline">{{ $siteName }}</span>
        </a>

        {{-- Search — full width on mobile, inline from md --}}
        <form action="{{ route('library.index') }}" method="GET" role="search" class="order-3 min-w-0 flex-1 basis-full md:order-none md:basis-auto">
            <label class="relative block">
                <span class="sr-only">Search prompts and creators</span>
                <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-creak" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                </svg>
                <input
                    type="search"
                    name="q"
                    value="{{ $query ?? '' }}"
                    maxlength="80"
                    placeholder="Search prompts, creators…"
                    class="w-full rounded-full border border-ink/15 bg-white py-2 pl-9 pr-3 text-sm text-ink placeholder-creak shadow-sm outline-none transition focus:border-saffron-deep focus:ring-2 focus:ring-saffron/40"
                >
            </label>
        </form>

        {{-- Desktop links --}}
        <div class="hidden items-center gap-1 md:flex">
            <a href="{{ route('packs.index') }}" class="rounded-full px-3 py-2 text-sm font-medium text-ink/80 transition hover:bg-ink/5 hover:text-ink">Packs</a>
            <a href="{{ route('pages.about') }}" class="rounded-full px-3 py-2 text-sm font-medium text-ink/80 transition hover:bg-ink/5 hover:text-ink">About</a>

            <div class="relative">
                <button @click="cats = !cats" @click.outside="cats = false" class="flex items-center gap-1 rounded-full px-3 py-2 text-sm font-medium text-ink/80 transition hover:bg-ink/5 hover:text-ink">
                    Categories
                    <svg class="size-4 text-creak" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                    </svg>
                </button>
                <div x-show="cats" x-cloak x-transition class="absolute right-0 mt-2 w-56 overflow-hidden rounded-2xl border border-ink/10 bg-white py-1.5 shadow-card-hover">
                    <a href="{{ route('library.index') }}" class="block px-4 py-2 text-sm text-ink/80 hover:bg-paper-deep hover:text-ink">All prompts</a>
                    @forelse($categories as $category)
                        <a href="{{ route('library.category', $category) }}" class="block px-4 py-2 text-sm text-ink/80 hover:bg-paper-deep hover:text-ink">{{ $category->name }}</a>
                    @empty
                        <p class="px-4 py-2 text-sm text-creak">No categories yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Auth area (desktop) --}}
        <div class="hidden shrink-0 items-center gap-2 md:flex">
            @auth
                <a href="{{ route('dashboard.prompts.create') }}" class="rounded-full bg-saffron px-3.5 py-2 text-sm font-bold text-ink shadow-[0_3px_0_0_#a16207] transition-all hover:-translate-y-0.5 hover:shadow-[0_5px_0_0_#a16207] active:translate-y-0.5 active:shadow-none">+ Add prompt</a>
                <a href="{{ route('purchases.index') }}" class="rounded-full px-3 py-2 text-sm font-medium text-ink/80 transition hover:bg-ink/5 hover:text-ink">Library</a>
                <a href="{{ route('dashboard') }}" class="rounded-full px-3 py-2 text-sm font-medium text-ink/80 transition hover:bg-ink/5 hover:text-ink">Dashboard</a>
                @if (auth()->user()->isModerator())
                    <a href="{{ route('admin.dashboard') }}" class="rounded-full border border-saffron-deep bg-saffron/20 px-3 py-2 text-sm font-semibold text-ink transition hover:bg-saffron/40">Admin</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-full border border-ink/15 px-3 py-2 text-sm font-medium text-ink/80 transition hover:bg-ink/5 hover:text-ink">Log out</button>
                </form>
            @endauth
            @guest
                <a href="{{ route('login') }}" class="rounded-full px-3 py-2 text-sm font-medium text-ink/80 transition hover:bg-ink/5 hover:text-ink">Log in</a>
                <a href="{{ route('register') }}" class="rounded-full bg-saffron px-3.5 py-2 text-sm font-bold text-ink shadow-[0_3px_0_0_#a16207] transition-all hover:-translate-y-0.5 hover:shadow-[0_5px_0_0_#a16207] active:translate-y-0.5 active:shadow-none">Sign up</a>
            @endguest
        </div>

        {{-- Mobile hamburger --}}
        <button @click="mobile = !mobile" class="flex size-10 shrink-0 items-center justify-center rounded-full text-ink transition hover:bg-ink/5 md:hidden" aria-label="Menu" :aria-expanded="mobile">
            <svg x-show="!mobile" class="size-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
            </svg>
            <svg x-show="mobile" x-cloak class="size-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    {{-- Mobile drawer: search row stays in the bar; links + categories live here --}}
    <div x-show="mobile" x-cloak x-transition class="border-t border-ink/10 bg-paper px-4 pb-4 pt-2 md:hidden">
        <div class="flex flex-wrap items-center gap-1">
            <a href="{{ route('packs.index') }}" class="rounded-full px-3 py-2 text-sm font-medium text-ink/80 transition hover:bg-ink/5 hover:text-ink">Packs</a>
            <a href="{{ route('pages.about') }}" class="rounded-full px-3 py-2 text-sm font-medium text-ink/80 transition hover:bg-ink/5 hover:text-ink">About</a>
            @auth
                <a href="{{ route('dashboard.prompts.create') }}" class="rounded-full bg-saffron px-3 py-2 text-sm font-bold text-ink shadow-[0_3px_0_0_#a16207]">+ Add prompt</a>
                <a href="{{ route('purchases.index') }}" class="rounded-full px-3 py-2 text-sm font-medium text-ink/80 transition hover:bg-ink/5 hover:text-ink">Library</a>
                <a href="{{ route('dashboard') }}" class="rounded-full px-3 py-2 text-sm font-medium text-ink/80 transition hover:bg-ink/5 hover:text-ink">Dashboard</a>
                @if (auth()->user()->isModerator())
                    <a href="{{ route('admin.dashboard') }}" class="rounded-full border border-saffron-deep bg-saffron/20 px-3 py-2 text-sm font-semibold text-ink">Admin</a>
                @endif
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="rounded-full border border-ink/15 px-3 py-2 text-sm font-medium text-ink/80">Log out</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="rounded-full px-3 py-2 text-sm font-medium text-ink/80">Log in</a>
            @endauth
        </div>

        @if ($categories->isNotEmpty())
            <p class="mt-3 px-1 font-mono text-[11px] font-bold uppercase tracking-widest text-ink/40">Categories</p>
            <div class="mt-1 flex flex-wrap gap-1.5">
                <a href="{{ route('library.index') }}" class="rounded-full border border-ink/10 bg-white px-3 py-1.5 text-xs font-medium text-ink/80 transition hover:border-saffron-deep hover:text-ink">All</a>
                @foreach ($categories as $category)
                    <a href="{{ route('library.category', $category) }}" class="rounded-full border border-ink/10 bg-white px-3 py-1.5 text-xs font-medium text-ink/80 transition hover:border-saffron-deep hover:text-ink">{{ $category->name }}</a>
                @endforeach
            </div>
        @endif
    </div>
</nav>
