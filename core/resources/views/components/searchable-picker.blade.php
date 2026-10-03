{{--
    R5 (v1.7.7 Bug Hunt Raid) — the admin combobox picker.

    The option list is SERVER-RENDERED ONCE by the caller (277 prompts render
    once, per the founder ruling); Alpine only filters visibility and writes
    the chosen value into the hidden input the form submits. Keyboard:
    ArrowUp/Down move the highlight, Enter picks, Escape closes. The
    selection shows as a chip with a clear button. No new dependencies.

    Slots: $options — the caller's [role="option"] buttons, each carrying
    `data-value`, `data-chip` (chip text), `data-search` (lowercased haystack)
    and `@click="choose($el)"`.
--}}
@props([
    'name',
    'label',
    'placeholder' => 'Search…',
    'value' => null,
    'chip' => null,
    'required' => false,
    'emptyText' => 'No matches.',
])

@php
    $currentValue = (string) old($name, $value ?? '');
    $currentChip = (string) old($name.'_chip', $chip ?? '');
@endphp

<div class="relative block" data-picker="{{ $name }}"
     x-data="comboboxPicker({ value: @js($currentValue), chip: @js($currentChip) })"
     @keydown.escape="open = false">
    <span class="text-sm font-semibold text-ink/90">
        {{ $label }}@if ($required)<span class="text-rose-600" aria-hidden="true"> *</span>@endif
    </span>

    <input type="hidden" name="{{ $name }}" :value="value" @if ($required) required @endif>

    <div class="relative mt-1">
        <input type="text" role="combobox" x-ref="input" x-model="query"
               aria-autocomplete="list" :aria-expanded="open ? 'true' : 'false'"
               aria-controls="{{ $name }}-listbox"
               @focus="open = true"
               @input="filter()"
               @keydown.arrow-down.prevent="open = true; move(1)"
               @keydown.arrow-up.prevent="open = true; move(-1)"
               @keydown.enter.prevent="pick()"
               placeholder="{{ $placeholder }}"
               class="block w-full rounded-xl border border-ink/15 bg-white px-3.5 py-2.5 pr-10 text-sm text-ink outline-none transition focus:border-saffron-deep focus:ring-2 focus:ring-saffron/40">

        <button type="button" x-show="value" x-cloak @click="clear()"
                aria-label="Clear {{ $label }}"
                class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full p-1 text-ink/40 transition hover:text-rose-600 focus-visible:ring-2 focus-visible:ring-saffron/70">
            <svg class="size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <p x-show="value" x-cloak x-text="chip"
       class="mt-1.5 inline-flex max-w-full items-center truncate rounded-full bg-saffron/20 px-3 py-1 text-xs font-semibold text-ink"></p>

    <div x-show="open" x-cloak id="{{ $name }}-listbox" role="listbox" x-ref="list"
         class="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-xl border border-ink/10 bg-white p-1 shadow-lg">
        {{ $options }}
        <p x-show="options().filter((option) => ! option.hidden).length === 0" x-cloak
           class="px-3 py-6 text-center text-xs text-ink/50">{{ $emptyText }}</p>
    </div>

    @error($name)
        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
    @enderror
</div>
