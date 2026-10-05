@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\SikkaPack> $packs */
    /** @var int|null $spendable */
    /** @var int $buyPaisa */
@endphp

<x-app-layout>
    <x-seo title="Sikka top-up" description="Top up your Sikka credits — spend them on prompts in the marketplace."/>

    <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6">
        <header>
            <p class="font-mono text-xs font-semibold uppercase tracking-[0.2em] text-saffron-deep">Sikka credits</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-ink">Top up your balance</h1>
            <p class="mt-1 max-w-2xl text-sm text-ink/60">
                Sikka credits are the library's spend-only credit: buy prompts, earn from sales,
                and withdraw eligible balances as NPR through the earnings tab.
            </p>
            @if ($spendable !== null)
                <p class="mt-3 inline-flex items-center gap-2 rounded-full border border-ink/10 bg-white px-4 py-1.5 text-sm text-ink/70">
                    Current balance: <x-sikka :amount="$spendable" :word="true"/>
                </p>
            @endif
        </header>

        <section class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($packs as $pack)
                <article class="flex flex-col rounded-2xl border border-ink/10 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-bold tracking-tight text-ink">{{ $pack->name }}</h2>
                    <p class="mt-2 flex items-baseline gap-1.5 text-2xl font-bold text-ink">
                        <x-sikka :amount="$pack->sikka_amount" :word="true" :size="22"/>
                    </p>
                    @if ($pack->bonus_sikka > 0)
                        <p class="mt-1 inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700">
                            + <x-sikka :amount="$pack->bonus_sikka"/> bonus credits
                        </p>
                    @endif

                    <div class="mt-5 flex-1"></div>

                    <p class="font-mono text-lg font-bold text-ink"><x-money :paisa="$pack->price_paisa"/></p>

                    @auth
                        <form method="POST" action="{{ route('checkout.sikka.packs.buy', $pack) }}" class="mt-3">
                            @csrf
                            <button class="w-full rounded-full bg-ink px-5 py-3 text-sm font-bold text-paper transition hover:bg-ink-soft">
                                Buy {{ $pack->name }}
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}"
                           class="mt-3 block w-full rounded-full bg-ink px-5 py-3 text-center text-sm font-bold text-paper transition hover:bg-ink-soft">
                            Sign in to top up
                        </a>
                    @endauth
                </article>
            @empty
                <p class="rounded-2xl border border-dashed border-ink/20 p-10 text-center text-sm text-ink/50 sm:col-span-2 lg:col-span-3">
                    No Sikka packs are open right now — check back soon.
                </p>
            @endforelse
        </section>
    </div>
</x-app-layout>
