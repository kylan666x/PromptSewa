<x-app-layout>
    @php $code = '401'; $title = 'Sign in first'; $message = 'You need to be logged in to see this page. Log in or create an account — your cart and progress are waiting.'; @endphp
    <div class="mx-auto flex max-w-2xl flex-col items-center px-4 py-24 text-center sm:px-6">
        <span class="flex size-16 items-center justify-center rounded-2xl bg-ink font-mono text-2xl font-bold text-saffron shadow-card-hover">✦</span>
        <p class="mt-6 font-mono text-sm font-bold uppercase tracking-[0.3em] text-saffron-deep">Error {{ $code }}</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight text-ink sm:text-4xl">{{ $title }}</h1>
        <p class="mt-3 max-w-md text-sm leading-relaxed text-ink/60">{{ $message }}</p>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <a href="{{ route('login') }}" class="rounded-full bg-saffron px-6 py-3 text-sm font-bold uppercase tracking-wide text-ink shadow-[0_4px_0_0_#a16207] transition-all hover:-translate-y-0.5 hover:shadow-[0_6px_0_0_#a16207] active:translate-y-0.5 active:shadow-none">Log in</a>
            <a href="{{ route('register') }}" class="rounded-full border-2 border-ink/80 bg-white px-6 py-3 text-sm font-bold uppercase tracking-wide text-ink shadow-[0_4px_0_0_rgba(23,23,21,0.8)] transition-all hover:-translate-y-0.5 hover:shadow-[0_6px_0_0_rgba(23,23,21,0.8)] active:translate-y-0.5 active:shadow-none">Create account</a>
        </div>
    </div>
</x-app-layout>
