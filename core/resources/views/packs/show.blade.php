<x-app-layout>
    @php
        /** @var \App\Models\Pack $pack */
        $totalValuePaisa = $pack->publishedPrompts->sum('price_cents');
        $savings = $totalValuePaisa - $pack->price_paisa;
    @endphp

    <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6">
        <a href="{{ route('packs.index') }}" class="text-sm font-medium text-ink/50 transition hover:text-ink">← All packs</a>

        <div class="mt-4 grid gap-10 lg:grid-cols-[1fr_320px]">
            <article>
                <span class="rounded-md bg-ink px-2.5 py-1 font-mono text-xs font-bold text-saffron">PACK · {{ $pack->publishedPrompts->count() }} prompts</span>
                <h1 class="mt-3 text-4xl font-bold tracking-tight text-ink">{{ $pack->name }}</h1>
                @if ($pack->description)
                    <p class="mt-4 max-w-2xl text-base leading-relaxed text-ink/70">{{ $pack->description }}</p>
                @endif

                <section class="mt-8" aria-label="Prompts in this pack">
                    <h2 class="font-mono text-xs font-semibold uppercase tracking-widest text-ink/50">What's inside</h2>
                    <ul class="mt-4 space-y-3">
                        @foreach ($pack->publishedPrompts as $prompt)
                            <li class="flex items-center justify-between gap-4 rounded-2xl border border-ink/10 bg-white px-5 py-4 shadow-sm transition hover:border-ink/25">
                                <div class="min-w-0">
                                    <a href="{{ route('prompts.show', $prompt) }}" class="truncate font-medium text-ink hover:text-saffron-deep">{{ $prompt->title }}</a>
                                    <p class="mt-0.5 truncate font-mono text-xs text-ink/50">{{ $prompt->category?->name ?? '' }} · {{ $prompt->typeLabel() }}</p>
                                </div>
                                <span class="shrink-0 font-mono text-sm {{ $prompt->price_cents === 0 ? 'text-emerald-700' : 'text-ink/70' }}">{{ $prompt->priceLabel() }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            </article>

            <aside class="h-fit rounded-3xl bg-ink p-6 shadow-card-hover lg:sticky lg:top-24">
                <p class="font-mono text-xs font-semibold uppercase tracking-widest text-paper/50">One-time price</p>
                <p class="mt-2 flex items-baseline gap-2">
                    <span class="font-mono text-3xl font-bold tracking-tight {{ $pack->price_paisa === 0 ? 'text-emerald-300' : 'text-saffron' }}">{{ $pack->priceLabel() }}</span>
                    @if ($savings > 0)
                        <span class="text-sm text-paper/40 line-through">Rs. {{ number_format(intdiv($totalValuePaisa, 100)) }}</span>
                    @endif
                </p>
                @if ($savings > 0)
                    <p class="mt-1 font-mono text-xs font-medium text-emerald-300">Save Rs. {{ number_format(intdiv($savings, 100)) }} vs buying individually</p>
                @endif
                <p class="mt-3 text-xs leading-relaxed text-paper/60">Includes every prompt in the pack — plus all future versions and new additions while it stays active.</p>

                <div class="mt-5">
                    @auth
                        <form method="POST" action="{{ route('checkout.packs.buy', $pack) }}">
                            @csrf
                            <button class="w-full rounded-full bg-saffron px-4 py-3 text-sm font-bold text-ink transition hover:bg-saffron-deep">
                                {{ $pack->price_paisa === 0 ? 'Get this pack' : 'Buy pack — '.$pack->priceLabel() }}
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="flex w-full items-center justify-center rounded-full bg-saffron px-4 py-3 text-sm font-bold text-ink transition hover:bg-saffron-deep">
                            Log in to buy
                        </a>
                    @endauth
                </div>
            </aside>
        </div>
    </div>
</x-app-layout>
