@props([
    'categories',
    'siteName' => 'PromptSewa',
    'brandLogoPath' => '',
    'brandMarkPath' => '',
])

{{-- T12 (v1.5.0): the burger drawer is RETIRED — mobile navigation is the
     bottom dock (x-mobile-dock). Category chips live at the top of the
     library page; Packs/About/Admin/Logout live in the profile "You" menu. --}}
<nav class="sticky top-0 z-40 border-b border-ink/10 bg-paper/90 backdrop-blur" x-data="{ cats: false }">
    <div class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 sm:px-6">
        {{-- Logo: desktop shows the full landscape logo (or fallback badge +
             wordmark); mobile shows the square mark only (S3). --}}
        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2 text-lg font-semibold tracking-tight text-ink" aria-label="{{ $siteName }} — home">
            @if ($brandLogoPath !== '')
                {{-- Full logo carries the site name visually — alt text only,
                     no adjacent wordmark (would duplicate for screen readers). --}}
                <img src="{{ asset('storage/'.$brandLogoPath) }}" alt="{{ $siteName }}" class="hidden h-9 w-auto object-contain md:block">
            @endif

            @if ($brandMarkPath !== '')
                <img src="{{ asset('storage/'.$brandMarkPath) }}" alt="" class="size-9 rounded-lg object-contain md:hidden" aria-hidden="true">
            @elseif ($brandLogoPath === '')
                <span class="flex size-8 items-center justify-center rounded-lg bg-saffron text-sm font-bold text-ink">{{ mb_substr($siteName, 0, 2) }}</span>
            @endif

            @if ($brandLogoPath === '')
                <span class="hidden sm:inline">{{ $siteName }}</span>
            @endif
        </a>

        {{-- Search — full width on mobile, inline from md; typeahead dropdown --}}
        <form action="{{ route('library.index') }}" method="GET" role="search"
              class="order-3 min-w-0 flex-1 basis-full md:order-none md:basis-auto"
              x-data="searchPreview(@js(route('search.preview')))"
              @click.outside="close()">
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
                    autocomplete="off"
                    x-model="q"
                    @input.debounce.300ms="onInput()"
                    @focus="if (items.length > 0) open = true"
                    @keydown="onKeydown($event)"
                    class="w-full rounded-full border border-ink/15 bg-white py-2 pl-9 pr-3 text-sm text-ink placeholder-creak shadow-sm outline-none transition focus:border-saffron-deep focus:ring-2 focus:ring-saffron/40"
                >

                {{-- Typeahead dropdown (TASK 2) --}}
                <div x-show="open" x-cloak x-transition.opacity
                     class="absolute top-full z-50 mt-2 w-full overflow-hidden rounded-2xl border border-ink/10 bg-paper shadow-xl">
                    <p x-show="loading" class="px-4 py-2 text-xs font-medium text-ink/40">Searching…</p>

                    <template x-if="prompts.length > 0">
                        <div>
                            <p class="px-4 pb-1 pt-3 font-mono text-[10px] font-bold uppercase tracking-widest text-ink/40">Prompts</p>
                            <template x-for="(prompt, index) in prompts" :key="'p'+index">
                                <a :href="prompt.url"
                                   class="flex items-center justify-between gap-3 px-4 py-2 text-sm text-ink transition hover:bg-saffron/10"
                                   :class="isActive(index) ? 'bg-saffron/10' : ''"
                                   @mouseenter="activeIndex = index">
                                    <span class="min-w-0 truncate" x-text="prompt.title"></span>
                                    <span class="shrink-0 font-mono text-[10px] font-bold uppercase tracking-wide"
                                          x-text="prompt.price > 0 ? 'Rs '+prompt.price : 'Free'"></span>
                                </a>
                            </template>
                        </div>
                    </template>

                    <template x-if="creators.length > 0">
                        <div>
                            <p class="px-4 pb-1 pt-3 font-mono text-[10px] font-bold uppercase tracking-widest text-ink/40">Creators</p>
                            <template x-for="(creator, index) in creators" :key="'c'+index">
                                <a :href="creator.url"
                                   class="flex items-center justify-between gap-3 px-4 py-2 text-sm text-ink transition hover:bg-saffron/10"
                                   :class="isActive(prompts.length + index) ? 'bg-saffron/10' : ''"
                                   @mouseenter="activeIndex = prompts.length + index">
                                    <span class="flex min-w-0 items-center gap-2">
                                        <span class="flex size-6 shrink-0 items-center justify-center overflow-hidden rounded-full bg-ink font-bold text-saffron">
                                            <template x-if="creator.avatar_url">
                                                <img :src="creator.avatar_url" :alt="'@'+(creator.username || creator.name)" class="size-full object-cover" loading="lazy">
                                            </template>
                                            <template x-if="! creator.avatar_url">
                                                <span class="text-[10px]" x-text="creator.initial"></span>
                                            </template>
                                        </span>
                                        <span class="min-w-0 truncate">
                                            <span x-text="creator.name"></span>
                                            <span class="font-mono text-xs text-ink/40" x-text="creator.username ? '@'+creator.username : ''"></span>
                                        </span>
                                    </span>
                                    <span class="shrink-0 font-mono text-[10px] uppercase tracking-wide text-ink/40"
                                          x-text="creator.prompts_count + (creator.prompts_count === 1 ? ' prompt' : ' prompts')"></span>
                                </a>
                            </template>
                        </div>
                    </template>

                </div>
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

                {{-- Account menu (X-style): avatar opens profile/library/settings links --}}
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" @click.outside="open = false" class="flex items-center gap-2 rounded-full py-1 pl-1 pr-2.5 transition hover:bg-ink/5" aria-label="Account menu" :aria-expanded="open">
                        <span class="flex size-8 items-center justify-center overflow-hidden rounded-full bg-ink text-xs font-bold text-saffron">
                            <x-user-avatar :user="auth()->user()" size="md"/>
                        </span>
                        <svg class="size-4 text-creak" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                        </svg>
                    </button>
                    <div x-show="open" x-cloak x-transition class="absolute right-0 mt-2 w-60 overflow-hidden rounded-2xl border border-ink/10 bg-white py-1.5 shadow-card-hover">
                        <div class="border-b border-ink/10 px-4 py-2.5">
                            <p class="flex items-center gap-1.5 truncate text-sm font-semibold text-ink">
                                <x-user-avatar :user="auth()->user()" size="sm"/>
                                {{ auth()->user()->name }}
                                <x-verified-badge :user="auth()->user()" size="xs"/>
                            </p>
                            <x-user-handle :user="auth()->user()" size="text-xs" class="block"/>
                        </div>
                        <a href="{{ route('creators.show', auth()->user()) }}" class="flex items-center gap-2.5 px-4 py-2 text-sm text-ink/80 transition hover:bg-paper-deep hover:text-ink">
                            <svg class="size-4 text-ink/40" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                            My profile
                        </a>
                        <a href="{{ route('dashboard.profile.edit') }}" class="flex items-center gap-2.5 px-4 py-2 text-sm text-ink/80 transition hover:bg-paper-deep hover:text-ink">
                            <svg class="size-4 text-ink/40" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
                            Edit profile
                        </a>
                        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 px-4 py-2 text-sm text-ink/80 transition hover:bg-paper-deep hover:text-ink">
                            <svg class="size-4 text-ink/40" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z"/></svg>
                            Dashboard
                        </a>
                        <a href="{{ route('purchases.index') }}" class="flex items-center gap-2.5 px-4 py-2 text-sm text-ink/80 transition hover:bg-paper-deep hover:text-ink">
                            <svg class="size-4 text-ink/40" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z"/></svg>
                            My library
                        </a>
                        @if (auth()->user()->isModerator())
                            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5 px-4 py-2 text-sm font-semibold text-saffron-deep transition hover:bg-saffron/15">
                                <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                Admin panel
                            </a>
                        @endif
                        <div class="border-t border-ink/10 mt-1.5 pt-1.5">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2 text-sm text-ink/80 transition hover:bg-paper-deep hover:text-ink">
                                    <svg class="size-4 text-ink/40" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/></svg>
                                    Log out
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endauth
            @guest
                <a href="{{ route('login') }}" class="rounded-full px-3 py-2 text-sm font-medium text-ink/80 transition hover:bg-ink/5 hover:text-ink">Log in</a>
                <a href="{{ route('register') }}" class="rounded-full bg-saffron px-3.5 py-2 text-sm font-bold text-ink shadow-[0_3px_0_0_#a16207] transition-all hover:-translate-y-0.5 hover:shadow-[0_5px_0_0_#a16207] active:translate-y-0.5 active:shadow-none">Sign up</a>
            @endguest
        </div>

    </div>

@if (session('impersonator_id'))
    @php
        $impersonator = \App\Models\User::find(session('impersonator_id'));
    @endphp
    @if ($impersonator)
        <div class="flex flex-wrap items-center justify-center gap-2 bg-[#1d9bf0] px-4 py-2 text-sm font-medium text-white">
            <span>Acting as <strong>@{{ auth()->user()->username ?? auth()->user()->name }}</strong> — returned session restores {{ $impersonator->name }}</span>
            <form method="POST" action="{{ route('impersonation.stop') }}">
                @csrf
                <button class="rounded-full bg-white px-3 py-1 text-xs font-bold text-[#1d9bf0] transition hover:bg-blue-50">Return to my account</button>
            </form>
        </div>
    @endif
@endif
</nav>
