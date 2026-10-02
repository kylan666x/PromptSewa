<x-app-layout>
    <x-seo :title="'Sign in'" robots="noindex, follow"/>
    <div class="mx-auto flex max-w-md flex-col px-4 py-16 sm:px-6">
        <h1 class="text-2xl font-bold tracking-tight text-ink">Welcome back</h1>
        <p class="mt-1 text-sm text-ink/60">Log in to access your library and dashboard.</p>

        <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5">
            @csrf

            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-ink/80">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                       class="w-full rounded-xl border border-ink/15 bg-white px-3 py-2.5 text-sm text-ink placeholder-creak shadow-sm outline-none transition focus:border-saffron-deep focus:ring-2 focus:ring-saffron/40">
                @error('email')<p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
            </div>

            {{-- A2 (v1.7.6): view-password toggle (x-password-input). --}}
            <x-password-input name="password" label="Password" autocomplete="current-password" required/>

            <label class="flex items-center gap-2 text-sm text-ink/60">
                <input type="checkbox" name="remember" class="size-4 rounded border-ink/20 accent-saffron-deep">
                Remember me
            </label>

            {{-- T3 (v1.7.3): bot check (off by default) — renders nothing when disabled. --}}
            <x-captcha form="login"/>

            <x-button type="submit" class="w-full">Log in</x-button>

            <p class="text-center text-sm text-ink/60">
                <a href="{{ route('password.request') }}" class="font-medium text-ink/70 underline decoration-saffron decoration-2 underline-offset-4 hover:text-ink">Forgot your password?</a>
            </p>

            <p class="text-center text-sm text-ink/60">
                New here?
                <a href="{{ route('register') }}" class="font-semibold text-ink underline decoration-saffron decoration-2 underline-offset-4 hover:text-saffron-deep">Create an account</a>
            </p>
        </form>
    </div>
</x-app-layout>
