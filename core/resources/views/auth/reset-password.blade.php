<x-app-layout>
    <x-seo :title="'Choose a new password'" robots="noindex, nofollow"/>
    <div class="mx-auto flex max-w-md flex-col px-4 py-16 sm:px-6">
        <h1 class="text-2xl font-bold tracking-tight text-ink">Choose a new password</h1>
        <p class="mt-1 text-sm text-ink/60">
            This link works once and expires in {{ (int) config('auth.passwords.users.expire', 60) }} minutes.
        </p>

        <form method="POST" action="{{ route('password.update', $token) }}" class="mt-8 space-y-5">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">

            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-ink/80">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="email"
                       class="w-full rounded-xl border border-ink/15 bg-white px-3 py-2.5 text-sm text-ink placeholder-creak shadow-sm outline-none transition focus:border-saffron-deep focus:ring-2 focus:ring-saffron/40">
                @error('email')<p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
            </div>

            {{-- A2: both new-secret fields carry the view toggle. --}}
            <x-password-input name="password" label="New password" autocomplete="new-password" required
                              minlength="8" placeholder="At least 8 characters"/>
            <x-password-input name="password_confirmation" label="Confirm new password"
                              autocomplete="new-password" required error-key="password"/>

            <p class="text-xs leading-relaxed text-ink/55">
                Pick something at least 8 characters long that doesn't contain your name or email address.
            </p>

            {{-- A1: bot check on the reset form (off by default — renders nothing). --}}
            <x-captcha form="reset"/>
            @error('captcha')<p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror

            <x-button type="submit" class="w-full">Update password</x-button>

            <p class="text-center text-sm text-ink/60">
                <a href="{{ route('password.request') }}" class="font-semibold text-ink underline decoration-saffron decoration-2 underline-offset-4 hover:text-saffron-deep">Request a new link</a>
            </p>
        </form>
    </div>
</x-app-layout>