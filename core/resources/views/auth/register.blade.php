<x-app-layout>
    <div class="mx-auto flex max-w-md flex-col px-4 py-16 sm:px-6">
        <h1 class="text-2xl font-bold tracking-tight text-ink">Create your account</h1>
        <p class="mt-1 text-sm text-ink/60">Join to buy, collect and sell prompts.</p>

        <form method="POST" action="{{ route('register.store') }}" class="mt-8 space-y-5">
            @csrf

            <div>
                <label for="name" class="mb-1.5 block text-sm font-medium text-ink/80">Name</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus
                       class="w-full rounded-xl border border-ink/15 bg-white px-3 py-2.5 text-sm text-ink placeholder-creak shadow-sm outline-none transition focus:border-saffron-deep focus:ring-2 focus:ring-saffron/40">
                @error('name')<p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-ink/80">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required
                       class="w-full rounded-xl border border-ink/15 bg-white px-3 py-2.5 text-sm text-ink placeholder-creak shadow-sm outline-none transition focus:border-saffron-deep focus:ring-2 focus:ring-saffron/40">
                @error('email')<p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="password" class="mb-1.5 block text-sm font-medium text-ink/80">Password</label>
                <input id="password" name="password" type="password" required
                       class="w-full rounded-xl border border-ink/15 bg-white px-3 py-2.5 text-sm text-ink placeholder-creak shadow-sm outline-none transition focus:border-saffron-deep focus:ring-2 focus:ring-saffron/40">
                @error('password')<p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-ink/80">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required
                       class="w-full rounded-xl border border-ink/15 bg-white px-3 py-2.5 text-sm text-ink placeholder-creak shadow-sm outline-none transition focus:border-saffron-deep focus:ring-2 focus:ring-saffron/40">
            </div>

            <x-button type="submit" class="w-full">Create account</x-button>

            <p class="text-center text-sm text-ink/60">
                Already have an account?
                <a href="{{ route('login') }}" class="font-semibold text-ink underline decoration-saffron decoration-2 underline-offset-4 hover:text-saffron-deep">Log in</a>
            </p>
        </form>
    </div>
</x-app-layout>
