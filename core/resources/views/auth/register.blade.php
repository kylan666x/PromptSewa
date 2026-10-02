<x-app-layout>
    <x-seo :title="'Create an account'" robots="noindex, follow"/>
    <div class="mx-auto flex max-w-md flex-col px-4 py-16 sm:px-6"
         x-data="signupForm()">
        <h1 class="text-2xl font-bold tracking-tight text-ink">Create your account</h1>
        <p class="mt-1 text-sm text-ink/60">Join to buy, collect and sell prompts.</p>

        <form method="POST" action="{{ route('register.store') }}" class="mt-8 space-y-5" @submit="submitting = true">
            @csrf

            <div>
                <label for="name" class="mb-1.5 block text-sm font-medium text-ink/80">Name</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus
                       x-model="name"
                       class="w-full rounded-xl border border-ink/15 bg-white px-3 py-2.5 text-sm text-ink placeholder-creak shadow-sm outline-none transition focus:border-saffron-deep focus:ring-2 focus:ring-saffron/40">
                @error('name')<p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="username" class="mb-1.5 block text-sm font-medium text-ink/80">Username</label>
                <div class="relative">
                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 font-mono text-sm text-ink/40">@</span>
                    <input id="username" name="username" type="text" value="{{ old('username') }}" required minlength="4" maxlength="30"
                           pattern="[A-Za-z0-9_-]+"
                           x-model="username"
                           autocomplete="off" spellcheck="false"
                           class="w-full rounded-xl border border-ink/15 bg-white px-3 py-2.5 pl-8 text-sm text-ink placeholder-creak shadow-sm outline-none transition focus:border-saffron-deep focus:ring-2 focus:ring-saffron/40"
                           placeholder="promptwizard">
                </div>
                <div class="mt-1.5 flex flex-wrap items-center gap-2" x-show="suggestions.length > 0" x-cloak>
                    <span class="text-[11px] text-ink/40">Available:</span>
                    <template x-for="suggestion in suggestions" :key="suggestion">
                        <button type="button" @click="username = suggestion"
                                class="rounded-full border border-ink/10 bg-paper-deep px-2.5 py-1 font-mono text-[11px] text-ink/70 transition hover:border-saffron-deep hover:text-saffron-deep"
                                x-text="'@' + suggestion"></button>
                    </template>
                </div>
                @error('username')<p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-ink/80">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required
                       x-model="email"
                       class="w-full rounded-xl border border-ink/15 bg-white px-3 py-2.5 text-sm text-ink placeholder-creak shadow-sm outline-none transition focus:border-saffron-deep focus:ring-2 focus:ring-saffron/40">
                @error('email')<p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
            </div>

            {{-- A2 (v1.7.6): view-password toggle; the S4 strength meter rides
                 in the slot below the field (it needs signupForm's scope). --}}
            <x-password-input name="password" label="Password" autocomplete="new-password"
                              required minlength="8" x-model="password">

                {{-- Animated strength meter (S4) --}}
                <div class="mt-2" x-show="password.length > 0" x-cloak>
                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-ink/10">
                        <div class="h-full rounded-full transition-all duration-150 ease-out"
                             :class="meterColor"
                             :style="'width:' + meterWidth + '%'"></div>
                    </div>
                    <p class="mt-1 text-[11px] font-medium" :class="meterTextClass" x-text="meterLabel"></p>
                    <ul class="mt-1.5 grid gap-0.5 text-[11px] text-ink/50">
                        <li :class="password.length >= 8 && 'text-emerald-700'">• At least 8 characters</li>
                        <li :class="hasUpperAndLower && 'text-emerald-700'">• Upper and lower case letters</li>
                        <li :class="hasNumber && 'text-emerald-700'">• A number</li>
                        <li :class="notNameOrEmail && 'text-emerald-700'">• Not your name or email</li>
                    </ul>
                </div>
            </x-password-input>

            <x-password-input name="password_confirmation" label="Confirm password"
                              autocomplete="new-password" required error-key="password"/>

            {{-- T3 (v1.7.3): bot check — widget sits above the submit button,
                 visible focus states (WCAG). Renders nothing when disabled. --}}
            <x-captcha form="register"/>

            <x-button type="submit" class="w-full">Create account</x-button>

            <p class="text-center text-sm text-ink/60">
                Already have an account?
                <a href="{{ route('login') }}" class="font-semibold text-ink underline decoration-saffron decoration-2 underline-offset-4 hover:text-saffron-deep">Log in</a>
            </p>
        </form>
    </div>
</x-app-layout>
