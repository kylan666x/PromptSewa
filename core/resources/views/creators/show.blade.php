<x-app-layout>
    @php
        /** @var \App\Models\User $creator */
        /** @var \Illuminate\Pagination\LengthAwarePaginator $prompts */
    @endphp

    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6">
        {{-- Profile header — inkwell panel --}}
        <div class="rounded-3xl bg-ink p-8 shadow-card-hover sm:p-10">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-center">
                <span class="flex size-20 shrink-0 items-center justify-center rounded-2xl bg-saffron text-3xl font-bold text-ink">
                    {{ mb_substr($creator->name, 0, 1) }}
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-3xl font-bold tracking-tight text-paper">{{ $creator->name }}</h1>
                        @if ($creator->isAtLeast(\App\Models\User::ROLE_CREATOR))
                            <span class="rounded-full bg-saffron px-3 py-1 font-mono text-xs font-bold text-ink">Creator</span>
                        @endif
                        @if ($creator->isModerator())
                            <span class="rounded-full border border-paper/30 px-3 py-1 font-mono text-xs font-medium text-paper/80">Moderator</span>
                        @endif
                    </div>
                    @if ($creator->bio)
                        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-paper/70">{{ $creator->bio }}</p>
                    @endif
                    <dl class="mt-4 flex flex-wrap gap-x-8 gap-y-2 font-mono text-sm">
                        <div class="flex items-baseline gap-2">
                            <dt class="text-paper/50">Prompts</dt>
                            <dd class="font-bold text-saffron">{{ number_format($stats['prompts']) }}</dd>
                        </div>
                        <div class="flex items-baseline gap-2">
                            <dt class="text-paper/50">Total sales</dt>
                            <dd class="font-bold text-saffron">{{ number_format($stats['total_sales']) }}</dd>
                        </div>
                        <div class="flex items-baseline gap-2">
                            <dt class="text-paper/50">Joined</dt>
                            <dd class="text-paper/80">{{ $stats['joined']->format('M Y') }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        {{-- Catalog --}}
        <section class="mt-10" aria-label="Prompts by {{ $creator->name }}">
            <div class="flex items-end justify-between gap-4">
                <h2 class="text-2xl font-bold tracking-tight text-ink">Prompts by {{ $creator->name }}</h2>
            </div>

            @if ($prompts->isEmpty())
                <div class="mt-6">
                    <x-empty-state
                        title="Nothing published yet"
                        description="{{ $creator->name }} hasn't published any prompts yet. Check back soon."
                    />
                </div>
            @else
                <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($prompts as $prompt)
                        <x-prompt-card :prompt="$prompt"/>
                    @endforeach
                </div>

                <div class="mt-10">
                    {{ $prompts->links() }}
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
