<x-app-layout>
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

            <div>
                <label for="password" class="mb-1.5 block text-sm font-medium text-ink/80">Password</label>
                <input id="password" name="password" type="password" required
                       class="w-full rounded-xl border border-ink/15 bg-white px-3 py-2.5 text-sm text-ink placeholder-creak shadow-sm outline-none transition focus:border-saffron-deep focus:ring-2 focus:ring-saffron/40">
            </div>

            <label class="flex items-center gap-2 text-sm text-ink/60">
                <input type="checkbox" name="remember" class="size-4 rounded border-ink/20 accent-saffron-deep">
                Remember me
            </label>

            <x-button type="submit" class="w-full">Log in</x-button>

            <p class="text-center text-sm text-ink/60">
                New here?
                <a href="{{ route('register') }}" class="font-semibold text-ink underline decoration-saffron decoration-2 underline-offset-4 hover:text-saffron-deep">Create an account</a>
            </p>
        </form>
    </div>
</x-app-layout>
