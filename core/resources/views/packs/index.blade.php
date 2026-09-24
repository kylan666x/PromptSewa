<x-app-layout>
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6">
        <header class="text-center">
            <h1 class="text-4xl font-bold tracking-tight text-ink">Prompt packs</h1>
            <p class="mx-auto mt-3 max-w-xl text-sm leading-relaxed text-ink/60">
                Curated collections of prompts at one price — buying a pack unlocks every prompt inside, now and in future versions.
            </p>
        </header>

        @if ($packs->isEmpty())
            <div class="mx-auto mt-12 max-w-lg rounded-2xl border border-dashed border-ink/20 bg-white p-10 text-center">
                <p class="text-sm text-ink/60">No packs are live yet — check back soon.</p>
                <a href="{{ route('library.index') }}" class="mt-3 inline-block text-sm font-semibold text-ink underline decoration-saffron decoration-2 underline-offset-4 hover:text-saffron-deep">Browse individual prompts →</a>
            </div>
        @else
            <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($packs as $pack)
                    <a href="{{ route('packs.show', $pack) }}"
                       class="group flex flex-col rounded-2xl border border-ink/10 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-saffron-deep hover:shadow-card-hover">
                        <div class="flex items-center justify-between">
                            <span class="rounded-md bg-ink px-2.5 py-1 font-mono text-xs font-bold text-saffron">PACK</span>
                            <span class="font-mono text-xs text-ink/50">{{ $pack->published_prompts_count }} prompts</span>
                        </div>
                        <h2 class="mt-4 text-xl font-bold tracking-tight text-ink group-hover:text-saffron-deep">{{ $pack->name }}</h2>
                        @if ($pack->description)
                            <p class="mt-2 line-clamp-3 text-sm leading-relaxed text-ink/60">{{ $pack->description }}</p>
                        @endif
                        <div class="mt-auto flex items-center justify-between pt-6">
                            <span class="font-mono text-2xl font-bold tracking-tight {{ $pack->price_paisa === 0 ? 'text-emerald-700' : 'text-ink' }}">{{ $pack->priceLabel() }}</span>
                            <span class="rounded-full bg-saffron px-4 py-2 text-sm font-bold text-ink transition group-hover:bg-saffron-deep">View pack →</span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
