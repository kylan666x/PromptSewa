<x-app-layout>
    <x-seo :title="'Forgot password'" robots="noindex, follow"/>
    <div class="mx-auto flex max-w-md flex-col px-4 py-16 sm:px-6">
        <h1 class="text-2xl font-bold tracking-tight text-ink">Forgot your password?</h1>
        <p class="mt-1 text-sm text-ink/60">
            Enter the email on your account and we'll send you a link to choose a new password.
        </p>

        <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5">
            @csrf

            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-ink/80">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                       class="w-full rounded-xl border border-ink/15 bg-white px-3 py-2.5 text-sm text-ink placeholder-creak shadow-sm outline-none transition focus:border-saffron-deep focus:ring-2 focus:ring-saffron/40">
                @error('email')<p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
                @error('captcha')<p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
            </div>

            {{-- A1: bot check on the reset form (off by default — renders nothing). --}}
            <x-captcha form="reset"/>

            <x-button type="submit" class="w-full">Email me a reset link</x-button>

            <p class="text-center text-sm text-ink/60">
                <a href="{{ route('login') }}" class="font-semibold text-ink underline decoration-saffron decoration-2 underline-offset-4 hover:text-saffron-deep">Back to sign in</a>
            </p>
        </form>
    </div>
</x-app-layout>