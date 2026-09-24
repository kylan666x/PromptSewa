@props([
    'categories',
    'siteName' => 'PromptSewa',
    'brandLogoPath' => '',
])

<nav class="sticky top-0 z-40 border-b border-ink/10 bg-paper/90 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-7xl items-center gap-4 px-4 sm:px-6">
        {{-- Logo (admin upload) or the wordmark badge --}}
        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2 text-lg font-semibold tracking-tight text-ink">
            @if ($brandLogoPath !== '')
                <img src="{{ asset('storage/'.$brandLogoPath) }}" alt="{{ $siteName }} logo" class="size-8 rounded-lg object-contain">
            @else
                <span class="flex size-8 items-center justify-center rounded-lg bg-saffron text-sm font-bold text-ink">{{ mb_substr($siteName, 0, 2) }}</span>
            @endif
            <span class="hidden sm:inline">{{ $siteName }}</span>
        </a>

        {{-- Search (submits to the Scout-backed library) --}}
        <form action="{{ route('library.index') }}" method="GET" role="search" class="min-w-0 flex-1 sm:max-w-md">
            <label class="relative block">
                <span class="sr-only">Search prompts</span>
                <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-creak" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                </svg>
                <input
                    type="search"
                    name="q"
                    value="{{ $query ?? '' }}"
                    maxlength="80"
                    placeholder="Search prompts…"
                    class="w-full rounded-full border border-ink/15 bg-white py-2 pl-9 pr-3 text-sm text-ink placeholder-creak shadow-sm outline-none transition focus:border-saffron-deep focus:ring-2 focus:ring-saffron/40"
                >
            </label>
        </form>

        {{-- Pages + Categories dropdown (Alpine — lightweight interactivity) --}}
        <div class="hidden items-center gap-1 lg:flex">
            <a href="{{ route('packs.index') }}" class="rounded-full px-3 py-2 text-sm font-medium text-ink/80 transition hover:bg-ink/5 hover:text-ink">Packs</a>
            <a href="{{ route('pages.about') }}" class="rounded-full px-3 py-2 text-sm font-medium text-ink/80 transition hover:bg-ink/5 hover:text-ink">About</a>
        </div>

        <div x-data="{ open: false }" class="relative hidden md:block">
            <button @click="open = !open" @click.outside="open = false" class="flex items-center gap-1 rounded-full px-3 py-2 text-sm font-medium text-ink/80 transition hover:bg-ink/5 hover:text-ink">
                Categories
                <svg class="size-4 text-creak" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                </svg>
            </button>
            <div x-show="open" x-cloak x-transition class="absolute right-0 mt-2 w-56 overflow-hidden rounded-2xl border border-ink/10 bg-white py-1.5 shadow-card-hover">
                <a href="{{ route('library.index') }}" class="block px-4 py-2 text-sm text-ink/80 hover:bg-paper-deep hover:text-ink">All prompts</a>
                @forelse($categories as $category)
                    <a href="{{ route('library.category', $category) }}" class="block px-4 py-2 text-sm text-ink/80 hover:bg-paper-deep hover:text-ink">{{ $category->name }}</a>
                @empty
                    <p class="px-4 py-2 text-sm text-creak">No categories yet.</p>
                @endforelse
            </div>
        </div>

        {{-- Auth area --}}
        <div class="flex shrink-0 items-center gap-2">
            @auth
                <a href="{{ route('dashboard.prompts.create') }}" class="rounded-full bg-saffron px-3.5 py-2 text-sm font-semibold text-ink shadow-sm transition hover:bg-saffron-deep">+ Add prompt</a>
                <a href="{{ route('purchases.index') }}" class="hidden rounded-full px-3 py-2 text-sm font-medium text-ink/80 transition hover:bg-ink/5 hover:text-ink sm:block">Library</a>
                <a href="{{ route('dashboard') }}" class="hidden rounded-full px-3 py-2 text-sm font-medium text-ink/80 transition hover:bg-ink/5 hover:text-ink sm:block">Dashboard</a>
                @if (auth()->user()->isModerator())
                    <a href="{{ route('admin.dashboard') }}" class="hidden rounded-full border border-saffron-deep bg-saffron/20 px-3 py-2 text-sm font-semibold text-ink transition hover:bg-saffron/40 sm:block">Admin</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-full border border-ink/15 px-3 py-2 text-sm font-medium text-ink/80 transition hover:bg-ink/5 hover:text-ink">Log out</button>
                </form>
            @endauth
            @guest
                <a href="{{ route('login') }}" class="hidden rounded-full px-3 py-2 text-sm font-medium text-ink/80 transition hover:bg-ink/5 hover:text-ink sm:block">Log in</a>
                <a href="{{ route('register') }}" class="rounded-full bg-saffron px-3.5 py-2 text-sm font-semibold text-ink shadow-sm transition hover:bg-saffron-deep">Sign up</a>
            @endguest
        </div>
    </div>
</nav>
