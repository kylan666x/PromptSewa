@props([
    'name' => 'password',
    // null = the caller renders its own <label> (the admin panels, which
    // hang a "✓ saved (encrypted)" chip off the label text).
    'label' => 'Password',
    'id' => null,
    // 'current-password' on sign-in, 'new-password' everywhere a NEW secret
    // is being typed. Never 'off' — password managers need the hint.
    'autocomplete' => 'current-password',
    'required' => false,
    'placeholder' => null,
    // Which validation bag this field's errors live in: the confirm field
    // reports under 'password', not 'password_confirmation'.
    'errorKey' => null,
])

@php
    /** A2 (v1.7.6) — the view-password toggle.
     *
     * House rules this component encodes:
     *  - the icons are TWO mutually exclusive svgs toggled with x-show,
     *    never one svg with a class getter (H4: conflicting Tailwind
     *    utilities on one node are decided by stylesheet order, which is
     *    how the heart went rose-but-outline);
     *  - `value` is stripped from the attribute bag, so no password field
     *    can ever ship a value in the served HTML (same family as the
     *    signup-meter plaintext ban) — even if a caller passes one;
     *  - the static type="password" is the no-JS default, x-bind:type is
     *    the toggle. Both are always present, so a JS-less browser still
     *    gets a masked field. */
    $inputId = $id ?? $name;
    $errorsOn = $errorKey ?? $name;
@endphp

<div x-data="{ shown: false }">
    @if ($label !== null)
        <label for="{{ $inputId }}" class="mb-1.5 block text-sm font-medium text-ink/80">{{ $label }}</label>
    @endif

    <div class="relative">
        <input id="{{ $inputId }}"
               name="{{ $name }}"
               type="password"
               autocomplete="{{ $autocomplete }}"
               @if ($required) required @endif
               @if ($placeholder) placeholder="{{ $placeholder }}" @endif
               x-bind:type="shown ? 'text' : 'password'"
               {{ $attributes->except('value')->class([
                   'w-full rounded-xl border border-ink/15 bg-white px-3 py-2.5 pr-11 text-sm text-ink placeholder-creak shadow-sm outline-none transition focus:border-saffron-deep focus:ring-2 focus:ring-saffron/40',
               ]) }}>

        <button type="button"
                x-on:click="shown = ! shown"
                aria-pressed="false"
                x-bind:aria-pressed="shown ? 'true' : 'false'"
                aria-label="Show password"
                x-bind:aria-label="shown ? 'Hide password' : 'Show password'"
                class="absolute right-1.5 top-1/2 flex size-8 -translate-y-1/2 items-center justify-center rounded-lg text-ink/45 transition hover:bg-ink/5 hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-saffron-deep focus-visible:ring-offset-1">
            {{-- eye (hidden state) --}}
            <svg x-show="! shown" x-cloak xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"
                 class="size-4" aria-hidden="true">
                <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
            {{-- eye-off (revealed state) --}}
            <svg x-show="shown" x-cloak xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"
                 class="size-4" aria-hidden="true">
                <path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/>
                <path d="M14.084 14.158a3 3 0 1 1-4.242-4.242"/>
                <path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/>
                <path d="m2 2 20 20"/>
            </svg>
        </button>
    </div>

    {{ $slot }}

    @error($errorsOn)
        <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
    @enderror
</div>