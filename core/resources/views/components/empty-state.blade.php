<div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-ink/20 bg-white px-6 py-16 text-center">
    <div class="flex size-14 items-center justify-center rounded-2xl bg-paper-deep">
        <svg class="size-7 text-creak" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
        </svg>
    </div>
    <h3 class="mt-4 text-lg font-semibold text-ink">{{ $title }}</h3>
    <p class="mt-1 max-w-sm text-sm leading-relaxed text-ink/60">{{ $description }}</p>

    @isset($actions)
        <div class="mt-6 flex flex-wrap justify-center gap-3">{{ $actions }}</div>
    @endisset
</div>
