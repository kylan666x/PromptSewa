<x-app-layout>
    <x-seo :title="$pack->name" :description="$pack->tagline ?? $pack->description ?? ('The '.$pack->name.' prompt pack.')"/>
    @php
        /**
         * G2 (v1.7.4) — pack landing v2.
         *
         * VOICE RULE: the inspiration contributed LAYOUT only (dark hero band,
         * sticky buy anchor, dark contents grid). Every number on this page is
         * computed from the database in PackController::show — the count is the
         * real published-prompt count, the star figure is a real AVG over the
         * pack's ratings ("No ratings yet" when the table is empty for these
         * prompts), and the savings line is integer SIKKA credits (S1b
         * v1.9.0 — credits are the price of record). There are NO
         * fabricated testimonials, seat counts or urgency banners here.
         *
         * @var \App\Models\Pack $pack
         * @var int $ratingCount
         * @var float|null $ratingAvg
         * @var \Illuminate\Support\Collection $relatedPacks
         */
        $promptCount = $pack->publishedPrompts->count();
        $typeIcons = [
            'image' => '▣', 'video' => '▶', 'agentic' => '⚙', 'skill' => '★', 'text' => '✎',
        ];
    @endphp

    <div class="mx-auto max-w-6xl px-4 pb-16 sm:px-6">
        <a href="{{ route('packs.index') }}" class="mt-6 inline-block text-sm font-medium text-ink/50 transition hover:text-ink">&larr; All packs</a>

        {{-- ── Ink hero band ──────────────────────────────────────────────── --}}
        <header class="relative mt-4 overflow-hidden rounded-3xl bg-ink px-6 py-10 sm:px-10 sm:py-14">
            <div class="pointer-events-none absolute inset-0 opacity-30 [background-image:radial-gradient(circle_at_1px_1px,rgba(245,197,24,0.35)_1px,transparent_1px)] [background-size:18px_18px]"></div>

            <div class="relative">
                <span class="inline-block rounded-full bg-saffron/15 px-3 py-1 font-mono text-xs font-bold uppercase tracking-widest text-saffron">Prompt pack</span>

                <h1 class="mt-4 font-display text-4xl font-bold tracking-tight text-paper sm:text-5xl">{{ $pack->name }}</h1>

                @if ($pack->tagline)
                    <p class="mt-3 max-w-2xl text-lg font-medium text-saffron">{{ $pack->tagline }}</p>
                @endif

                {{-- Stat chips — every figure is a real aggregate. --}}
                <ul class="mt-6 flex flex-wrap gap-2 font-mono text-xs">
                    <li class="rounded-full bg-paper/10 px-3 py-1.5 text-paper/80">
                        {{ $promptCount }} {{ Str::plural('prompt', $promptCount) }}
                    </li>
                    <li class="rounded-full bg-paper/10 px-3 py-1.5 text-paper/80">
                        @if ($ratingAvg !== null)
                            ★ {{ number_format($ratingAvg, 1) }} <span class="text-paper/50">({{ $ratingCount }} {{ Str::plural('rating', $ratingCount) }})</span>
                        @else
                            ★ No ratings yet
                        @endif
                    </li>
                    <li class="rounded-full bg-paper/10 px-3 py-1.5 text-paper/80">
                        Future versions included
                    </li>
                </ul>

                @if ($pack->hero_copy)
                    <div class="mt-6 max-w-2xl whitespace-pre-line text-sm leading-relaxed text-paper/70">{{ $pack->hero_copy }}</div>
                @endif
            </div>
        </header>

        <div class="mt-10 grid gap-10 lg:grid-cols-[1fr_320px]">
            <div class="min-w-0">
                {{-- ── Description ─────────────────────────────────────────── --}}
                @if ($pack->description)
                    <p class="max-w-2xl text-base leading-relaxed text-ink/70">{{ $pack->description }}</p>
                @endif

                {{-- ── T8 (v1.5.0): Product/Offer JSON-LD — retained. ──────────
                     S1b (v1.9.0) bug fix: the array is built in a stored PHP
                     block because Blade compiles a literal `'@context'` key in
                     a raw echo as the context DIRECTIVE — the script tag was
                     carrying generated PHP source instead of the schema URL,
                     so the JSON-LD was invalid. Stored blocks are not
                     directive-compiled, so the key survives. --}}
                @php
                    $packJsonLd = [
                        '@context' => 'https://schema.org',
                        '@type' => 'Product',
                        'name' => $pack->name,
                        'description' => $pack->tagline ?? $pack->description ?? $pack->name,
                        'brand' => ['@type' => 'Brand', 'name' => ($siteName ?? 'PromptSewa')],
                        'offers' => [
                            '@type' => 'Offer',
                            // The price of record is Sikka — the NPR mirror is
                            // not a stable figure (credits are bought at the
                            // desk's rate), so no NPR leaks into structured
                            // data either. "SIKKA" is a token code,
                            // documented here because schema.org expects a
                            // currency code and there is no ISO one for
                            // credits.
                            'price' => (string) $pack->priceSikka(),
                            'priceCurrency' => 'SIKKA',
                            'availability' => 'https://schema.org/InStock',
                            'url' => url()->current(),
                        ],
                    ];
                @endphp
                <script type="application/ld+json">{!! json_encode($packJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>

                {{-- ── Contents: dark card grid ────────────────────────────── --}}
                <section class="mt-10" aria-label="Prompts in this pack">
                    <div class="flex items-baseline justify-between gap-4">
                        <h2 class="font-mono text-xs font-semibold uppercase tracking-widest text-ink/50">What&rsquo;s inside</h2>
                        <a href="{{ route('packs.index') }}" class="shrink-0 text-sm font-medium text-saffron-deep transition hover:text-ink">
                            All packs &rarr;
                        </a>
                    </div>

                    @if ($promptCount === 0)
                        <p class="mt-4 rounded-2xl border border-ink/10 bg-white px-5 py-6 text-sm text-ink/60">
                            This pack has no published prompts yet. Check back soon.
                        </p>
                    @else
                        <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                            @foreach ($pack->publishedPrompts as $prompt)
                                <li class="rounded-2xl bg-ink p-4 shadow-card transition hover:bg-ink-soft">
                                    <div class="flex items-start gap-3">
                                        <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-paper/10 font-mono text-sm text-saffron" aria-hidden="true">
                                            {{ $typeIcons[$prompt->type] ?? $typeIcons['text'] }}
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <a href="{{ route('prompts.show', $prompt) }}" class="block truncate font-medium text-paper transition hover:text-saffron">{{ $prompt->title }}</a>
                                            <p class="mt-0.5 truncate font-mono text-[11px] uppercase tracking-wide text-paper/50">
                                                {{ $prompt->category?->name ?? 'Uncategorised' }}
                                            </p>
                                        </div>
                                    </div>
                                    <p class="mt-3 text-xs {{ $prompt->price_sikka === 0 ? 'text-emerald-300' : 'text-paper/70' }}">
                                        @if ($prompt->price_sikka === 0)
                                            Free
                                        @else
                                            <x-sikka :amount="$prompt->price_sikka"/>
                                        @endif
                                    </p>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                {{-- ── Honest checklist: licence FACTS only (no superlatives) ── --}}
                <section class="mt-10 rounded-2xl border border-ink/10 bg-white p-5" aria-label="What your purchase includes">
                    <h2 class="font-mono text-xs font-semibold uppercase tracking-widest text-ink/50">What your purchase includes</h2>
                    <ul class="mt-4 space-y-2.5 text-sm text-ink/75">
                        <li class="flex gap-3"><span class="text-emerald-600" aria-hidden="true">✓</span><span>Every prompt listed above, downloaded as a text file from your library.</span></li>
                        <li class="flex gap-3"><span class="text-emerald-600" aria-hidden="true">✓</span><span>Later versions of those prompts: your library always serves the newest published version.</span></li>
                        <li class="flex gap-3"><span class="text-emerald-600" aria-hidden="true">✓</span><span>Personal and commercial use. Reselling or redistributing the prompt text is not permitted.</span></li>
                        <li class="flex gap-3"><span class="text-emerald-600" aria-hidden="true">✓</span><span>Your licence is tied to the account that bought it — one grant per prompt.</span></li>
                        <li class="flex gap-3"><span class="text-rose-500" aria-hidden="true">✕</span><span class="text-ink/60">No seat transfers: sharing your login moves nothing, because the licence follows the account.</span></li>
                    </ul>
                </section>

                {{-- ── FAQ: real policy answers, straight from the code ─────── --}}
                <section class="mt-10" aria-label="Frequently asked questions">
                    <h2 class="font-mono text-xs font-semibold uppercase tracking-widest text-ink/50">Questions before you buy</h2>
                    <dl class="mt-4 divide-y divide-ink/10 rounded-2xl border border-ink/10 bg-white">
                        <div class="px-5 py-4">
                            <dt class="font-semibold text-ink">What exactly do I get?</dt>
                            <dd class="mt-1 text-sm leading-relaxed text-ink/70">A plain-text download of each prompt in the pack, plus its variable list. Nothing is hidden behind a subscription.</dd>
                        </div>
                        <div class="px-5 py-4">
                            <dt class="font-semibold text-ink">What happens when a prompt in the pack is updated?</dt>
                            <dd class="mt-1 text-sm leading-relaxed text-ink/70">Nothing to re-buy. Re-downloading from your library serves the newest published version, so improvements land automatically.</dd>
                        </div>
                        <div class="px-5 py-4">
                            <dt class="font-semibold text-ink">How can I pay?</dt>
                            <dd class="mt-1 text-sm leading-relaxed text-ink/70">
                                Top up Sikka credits on the <a href="{{ route('sikka.topup') }}" class="font-semibold text-ink underline decoration-saffron decoration-2 underline-offset-4 hover:text-saffron-deep">top-up page</a>
                                — eSewa, or a manual transfer where you upload your payment proof and an admin approves it. Then pay for this pack with your credits at checkout,
                                where the balance leaves your account the moment the order is paid.
                            </dd>
                        </div>
                        <div class="px-5 py-4">
                            <dt class="font-semibold text-ink">Can I get a refund?</dt>
                            <dd class="mt-1 text-sm leading-relaxed text-ink/70">Because these are instant digital downloads, a completed purchase is not refundable — contact support before buying if you are unsure the pack fits your work.</dd>
                        </div>
                        <div class="px-5 py-4">
                            <dt class="font-semibold text-ink">Is the pack price really lower than buying separately?</dt>
                            <dd class="mt-1 text-sm leading-relaxed text-ink/70">
                                @if ($savingsSikka > 0)
                                    Yes — the contents add up to <x-sikka :amount="$individualSumSikka"/> bought one by one, which is <x-sikka :amount="$savingsSikka"/> more than this pack.
                                @else
                                    The pack price is <x-sikka :amount="$pack->priceSikka()"/>; the buy card shows the separately-priced total so you can compare honestly.
                                @endif
                            </dd>
                        </div>
                    </dl>
                </section>

                {{-- ── Related packs (real rows, never invented) ────────────── --}}
                @if ($relatedPacks->isNotEmpty())
                    <section class="mt-12" aria-label="Other packs">
                        <h2 class="font-mono text-xs font-semibold uppercase tracking-widest text-ink/50">You might also like</h2>
                        <ul class="mt-4 grid gap-3 sm:grid-cols-3">
                            @foreach ($relatedPacks as $other)
                                <li>
                                    <a href="{{ route('packs.show', $other) }}" class="block h-full rounded-2xl border border-ink/10 bg-white p-4 transition hover:border-saffron-deep">
                                        <p class="truncate font-medium text-ink">{{ $other->name }}</p>
                                        <p class="mt-1 line-clamp-2 text-xs text-ink/60">{{ $other->tagline ?? 'Prompt pack' }}</p>
                                        <p class="mt-3 flex items-center gap-1.5 text-xs {{ $other->isFree() ? 'text-emerald-700' : 'text-ink/70' }}">
                                            @if ($other->isFree())
                                                <span class="font-mono">Free</span>
                                            @else
                                                <x-sikka :amount="$other->priceSikka()"/>
                                            @endif
                                            <span class="text-ink/40">·</span>
                                            <span>{{ $other->published_prompts_count }} {{ Str::plural('prompt', $other->published_prompts_count) }}</span>
                                        </p>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>

            {{-- ── Sticky buy card ───────────────────────────────────────── --}}
            <aside class="order-first lg:order-none lg:sticky lg:top-24 lg:h-fit">
                <div class="rounded-3xl bg-ink p-6 shadow-card-hover">
                    <p class="font-mono text-xs font-semibold uppercase tracking-widest text-paper/50">One-time price</p>

                    <p class="mt-2 flex flex-wrap items-baseline gap-2">
                        @if ($pack->isFree())
                            <span class="font-mono text-3xl font-bold tracking-tight text-emerald-300">Free</span>
                        @else
                            <span class="text-3xl font-bold tracking-tight text-saffron"><x-sikka :amount="$pack->priceSikka()" :word="true" :size="24"/></span>
                        @endif
                        @if ($savingsSikka > 0)
                            <span class="text-sm text-paper/40 line-through"><x-sikka :amount="$individualSumSikka"/></span>
                        @endif
                    </p>

                    @if ($savingsSikka > 0)
                        <p class="mt-1 text-xs font-medium text-emerald-300">
                            Save <x-sikka :amount="$savingsSikka"/> vs buying individually
                        </p>
                    @elseif ($individualSumSikka > 0)
                        <p class="mt-1 text-xs text-paper/50">
                            Contents total <x-sikka :amount="$individualSumSikka"/> separately
                        </p>
                    @endif

                    <p class="mt-3 text-xs leading-relaxed text-paper/60">
                        One purchase unlocks every prompt above, and every future version of them.
                    </p>

                    <div class="mt-5">
                        @auth
                            <form method="POST" action="{{ route('checkout.packs.buy', $pack) }}">
                                @csrf
                                <button class="w-full rounded-full bg-saffron px-4 py-3 text-sm font-bold text-ink transition hover:bg-saffron-deep">
                                    @if ($pack->isFree())
                                        Get this pack
                                    @else
                                        Buy pack — <x-sikka :amount="$pack->priceSikka()"/> credits
                                    @endif
                                </button>
                            </form>
                        @else
                            <a href="{{ route('login') }}" class="flex w-full items-center justify-center rounded-full bg-saffron px-4 py-3 text-sm font-bold text-ink transition hover:bg-saffron-deep">
                                Log in to buy
                            </a>
                        @endauth
                    </div>
                </div>
            </aside>
        </div>
    </div>
</x-app-layout>