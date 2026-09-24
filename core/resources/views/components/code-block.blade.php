@props(['copyText' => null, 'label' => 'Copy'])

@php
    // The raw payload to copy — Blade-escaped in HTML, decoded to real text
    // in JS. Prompt bodies are inert data; never rendered as markup.
    $payload = $copyText ?? $slot->toHtmlString();
@endphp

<div
    x-data="{ copied: false, copy() { navigator.clipboard.writeText(@js($payload)).then(() => { this.copied = true; setTimeout(() => this.copied = false, 2000); }); } }"
    class="relative"
>
    <button
        type="button"
        @click="copy()"
        class="absolute right-3 top-3 z-10 inline-flex items-center gap-1.5 rounded-full border border-paper/20 bg-ink-soft/90 px-3 py-1.5 font-mono text-xs font-semibold text-paper backdrop-blur transition hover:border-saffron hover:text-saffron"
        :class="copied && 'border-emerald-400/60 text-emerald-300'"
        aria-live="polite"
    >
        <svg x-show="!copied" class="size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184"/>
        </svg>
        <svg x-show="copied" x-cloak class="size-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M19.916 4.626a.75.75 0 0 1 .208 1.04l-9 13.5a.75.75 0 0 1-1.154.114l-6-6a.75.75 0 0 1 1.06-1.06l5.353 5.353 8.493-12.74a.75.75 0 0 1 1.04-.207Z" clip-rule="evenodd"/>
        </svg>
        <span x-text="copied ? 'Copied!' : @js($label)"></span>
    </button>

    <pre {{ $attributes->merge(['class' => 'overflow-x-auto whitespace-pre-wrap rounded-2xl bg-ink p-5 pt-12 font-mono text-sm leading-relaxed text-paper/90']) }}>{{ $payload }}</pre>
</div>
