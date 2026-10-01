{{--
    T12 (v1.5.0) — mobile bottom dock. Replaces the retired burger drawer.

    Slot map (exactly five):
      1. Home     2. Library   3. CENTER CTA   4. My library/You   5. Profile

    Constraints:
      - ink ground (#1c1917 family), saffron on the CENTER CTA ONLY
      - WCAG: every target ≥ 44px, aria-labels, visible focus rings
      - safe-area padding for notched devices
      - body wrapper adds pb-24 md:pb-0 so content clears the dock
      - z-40: sits UNDER the typeahead dropdown (z-50)
      - md:hidden — desktop keeps the top navbar
      - no JavaScript: plain links + a POST form for logout

    The authed center CTA is "+ Add prompt" (the primary creator action);
    for guests it is "Sign up". Slot 4 ("You") lands on the profile page
    where Packs/About/Admin/Logout live — staff-only Admin row is gated
    server-side in the profile view, never CSS-hidden.
--}}
@php
    $currentRoute = request()->route()?->getName();
@endphp

@props(['siteName' => 'PromptSewa'])

<nav aria-label="Mobile primary" class="fixed inset-x-0 bottom-0 z-40 border-t border-white/10 bg-ink pb-[env(safe-area-inset-bottom)] md:hidden">
    <div class="mx-auto grid max-w-lg grid-cols-5 items-stretch">
        {{-- Slot 1: Home --}}
        <a href="{{ route('home') }}" aria-label="Home" aria-current="{{ $currentRoute === 'home' ? 'page' : 'false' }}"
           class="dock-tab {{ $currentRoute === 'home' ? 'text-saffron' : 'text-paper/70' }}">
            <svg class="size-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75"/>
            </svg>
            <span class="text-[10px] font-medium">Home</span>
        </a>

        {{-- Slot 2: Library --}}
        <a href="{{ route('library.index') }}" aria-label="Browse prompts" aria-current="{{ $currentRoute === 'library.index' ? 'page' : 'false' }}"
           class="dock-tab {{ $currentRoute === 'library.index' ? 'text-saffron' : 'text-paper/70' }}">
            <svg class="size-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25"/>
            </svg>
            <span class="text-[10px] font-medium">Library</span>
        </a>

        {{-- Slot 3: CENTER CTA — the ONLY saffron element --}}
        @auth
            <a href="{{ route('dashboard.prompts.create') }}" aria-label="Add prompt"
               class="mx-auto my-1.5 flex h-12 w-12 flex-col items-center justify-center rounded-full bg-saffron text-ink shadow-[0_3px_0_0_#a16207] transition-all focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-saffron active:translate-y-0.5 active:shadow-none">
                <svg class="size-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
            </a>
        @else
            <a href="{{ route('register') }}" aria-label="Sign up"
               class="mx-auto my-1.5 flex h-12 items-center rounded-full bg-saffron px-4 text-xs font-bold text-ink shadow-[0_3px_0_0_#a16207] transition-all focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-saffron active:translate-y-0.5 active:shadow-none">
                Sign up
            </a>
        @endauth

        {{-- Slot 4: My library (purchases) / You --}}
        @auth
            <a href="{{ route('purchases.index') }}" aria-label="My library" aria-current="{{ $currentRoute === 'purchases.index' ? 'page' : 'false' }}"
               class="dock-tab {{ $currentRoute === 'purchases.index' ? 'text-saffron' : 'text-paper/70' }}">
                <svg class="size-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z"/>
                </svg>
                <span class="text-[10px] font-medium">Library</span>
            </a>
        @else
            <a href="{{ route('login') }}" aria-label="Log in"
               class="dock-tab text-paper/70">
                <svg class="size-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/>
                </svg>
                <span class="text-[10px] font-medium">Log in</span>
            </a>
        @endauth

        {{-- Slot 5: You (P4, v1.7.1: lands on the PUBLIC profile — the
             identity surface. Edit profile remains reachable from the
             profile page button and the You menu on it.) --}}
        <a href="{{ auth()->check() ? route('creators.show', auth()->user()) : route('login') }}"
           aria-label="Account"
           aria-current="{{ $currentRoute === 'creators.show' ? 'page' : 'false' }}"
           class="dock-tab {{ $currentRoute === 'creators.show' ? 'text-saffron' : 'text-paper/70' }}">
            <svg class="size-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
            </svg>
            <span class="text-[10px] font-medium">You</span>
        </a>
    </div>
</nav>
