<x-app-layout>
    <x-seo :title="'Check your email'" robots="noindex, nofollow"/>
    <div class="mx-auto flex max-w-md flex-col px-4 py-16 sm:px-6">
        <div class="flex size-11 items-center justify-center rounded-xl bg-saffron/30 text-ink" aria-hidden="true">
            <svg class="size-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/>
                <path d="m3 7 9 6 9-6"/>
            </svg>
        </div>

        <h1 class="mt-5 text-2xl font-bold tracking-tight text-ink">Check your email</h1>
        <p class="mt-2 text-sm leading-relaxed text-ink/70">
            If that address belongs to an account, a reset link is on its way. It works once and
            expires in {{ (int) config('auth.passwords.users.expire', 60) }} minutes.
        </p>
        <p class="mt-3 rounded-xl border border-ink/10 bg-paper-deep px-4 py-3 text-xs leading-relaxed text-ink/60">
            Nothing arriving? Check spam, then try again — we only send one link per minute, and
            we deliberately don't say whether the address is registered.
        </p>

        <div class="mt-6 flex flex-wrap items-center gap-3">
            <x-button :href="route('login')" variant="secondary">Back to sign in</x-button>
            <a href="{{ route('password.request') }}" class="text-sm font-medium text-ink/70 underline decoration-saffron decoration-2 underline-offset-4 hover:text-ink">Send it again</a>
        </div>
    </div>
</x-app-layout>