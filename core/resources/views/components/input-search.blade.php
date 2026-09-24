@props([
    'name' => 'q',
    'label' => 'Search',
    'placeholder' => 'Search prompts…',
    'id' => null,
])

<div {{ $attributes->only('class') }}>
    <label for="{{ $id ?? $name }}" class="mb-1.5 block text-sm font-medium text-ink/80">
        {{ $label }}
    </label>

    <div class="relative">
        <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-creak" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
        </svg>
        <input
            type="search"
            name="{{ $name }}"
            id="{{ $id ?? $name }}"
            value="{{ old($name) }}"
            maxlength="80"
            placeholder="{{ $placeholder }}"
            {{ $attributes->except('class') }}
            class="w-full rounded-full border border-ink/15 bg-white py-2.5 pl-9 pr-3 text-sm text-ink placeholder-creak shadow-sm outline-none transition focus:border-saffron-deep focus:ring-2 focus:ring-saffron/40"
        >
    </div>
</div>
