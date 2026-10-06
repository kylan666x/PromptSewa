@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\MembershipPlan> $plans */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Membership> $memberships */
    /** @var \Illuminate\Support\Collection<int, string> $badges */
    /** @var \Illuminate\Support\Collection<int, string> $frames */
@endphp

<x-app-layout>
    <x-seo title="Memberships" description="Membership plans with Sikka credit stipends, unlimited unlocks and profile perks."/>

    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6">
        <header>
            <p class="font-mono text-xs font-semibold uppercase tracking-[0.2em] text-saffron-deep">Memberships</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-ink">Belong to the library</h1>
            <p class="mt-1 max-w-2xl text-sm text-ink/60">
                Plans are bought with Sikka credits at checkout — top up first if your balance is short.
                A plan's stipend lands as spendable credits every 30 days while the membership is active.
            </p>
        </header>

        @if ($memberships->isNotEmpty())
            <section class="mt-8">
                <h2 class="text-sm font-semibold text-ink">Your memberships</h2>
                <ul class="mt-3 flex flex-wrap gap-2">
                    @foreach ($memberships as $membership)
                        @if ($membership->plan)
                            <li class="rounded-full border border-ink/10 bg-white px-3.5 py-1.5 text-xs text-ink/70">
                                <span class="font-semibold text-ink">{{ $membership->plan->name }}</span>
                                · <span class="font-mono uppercase">{{ $membership->status }}</span>
                                @if ($membership->ends_at)
                                    · until {{ $membership->ends_at->format('M j, Y') }}
                                @endif
                            </li>
                        @endif
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="mt-8 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            @forelse ($plans as $plan)
                <article class="flex flex-col rounded-2xl border border-ink/10 bg-white p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold tracking-tight text-ink">{{ $plan->name }}</h2>
                            <p class="mt-0.5 font-mono text-xs uppercase tracking-wider text-ink/50">{{ $plan->duration_days }} days</p>
                        </div>
                        {{-- S1b (v1.9.0): plans price in Sikka — never NPR. --}}
                        <p class="shrink-0 text-xl font-bold text-ink"><x-sikka :amount="$plan->priceSikka()" :word="true" :size="22"/></p>
                    </div>

                    <ul class="mt-5 space-y-2 text-sm text-ink/70">
                        @if ($plan->stipend_sikka > 0)
                            <li class="flex items-center gap-2">
                                <span class="text-emerald-700">✓</span>
                                <span><x-sikka :amount="$plan->stipend_sikka" :word="true"/> credits every 30 days</span>
                            </li>
                        @endif
                        @if ($plan->hasUnlimitedUnlock())
                            <li class="flex items-center gap-2"><span class="text-emerald-700">✓</span> Unlimited unlock — prompts cost no Sikka credits</li>
                        @endif
                        @if (($badgeId = $plan->perk(\App\Models\MembershipPlan::PERK_BADGE_ID)) && ($badges[$badgeId] ?? null))
                            <li class="flex items-center gap-2"><span class="text-emerald-700">✓</span> {{ $badges[$badgeId] }} badge</li>
                        @endif
                        @if (($frameId = $plan->perk(\App\Models\MembershipPlan::PERK_FRAME_ID)) && ($frames[$frameId] ?? null))
                            <li class="flex items-center gap-2"><span class="text-emerald-700">✓</span> {{ $frames[$frameId] }} frame</li>
                        @endif
                        @if ($plan->perk(\App\Models\MembershipPlan::PERK_GRANT_VERIFIED))
                            <li class="flex items-center gap-2"><span class="text-emerald-700">✓</span> Verified account badge</li>
                        @endif
                    </ul>

                    <div class="mt-6 flex-1"></div>

                    @auth
                        <form method="POST" action="{{ route('checkout.memberships.buy', $plan) }}">
                            @csrf
                            <button class="w-full rounded-full bg-ink px-5 py-3 text-sm font-bold text-paper transition hover:bg-ink-soft">
                                @if ($plan->isFree())
                                    Activate {{ $plan->name }}
                                @else
                                    Get {{ $plan->name }} — <x-sikka :amount="$plan->priceSikka()"/> credits
                                @endif
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}"
                           class="block w-full rounded-full bg-ink px-5 py-3 text-center text-sm font-bold text-paper transition hover:bg-ink-soft">
                            Sign in to join
                        </a>
                    @endauth
                </article>
            @empty
                <p class="rounded-2xl border border-dashed border-ink/20 p-10 text-center text-sm text-ink/50 md:col-span-2 lg:col-span-3">
                    No membership plans are open right now — check back soon.
                </p>
            @endforelse
        </section>
    </div>
</x-app-layout>
