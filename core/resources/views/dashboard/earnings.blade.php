<x-app-layout>
    <x-seo title="Earnings" robots="noindex, follow"/>

    @php
        /** @var int $balance */
        /** @var int $available */
        /** @var int $lifetimeCredits */
        /** @var int $salesCount */
        /** @var int $minPayoutPaisa */
        /** @var \Illuminate\Support\Collection<int, \App\Models\Payout> $payouts */
        /** @var \Illuminate\Support\Collection<int, \App\Models\WalletTransaction> $ledger */
    @endphp

    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="font-mono text-xs font-semibold uppercase tracking-[0.2em] text-saffron-deep">Creator earnings</p>
                <h1 class="mt-1 text-3xl font-bold tracking-tight text-ink">Your money</h1>
                <p class="mt-1 text-sm text-ink/60">Every credit is a row in an append-only ledger — the numbers below are its sum.</p>
            </div>
            <a href="{{ route('dashboard') }}"
               class="rounded-full border border-ink/15 px-4 py-2 text-sm font-semibold text-ink/70 transition hover:border-ink/30">&larr; Back to dashboard</a>
        </header>

        {{-- Balance cards --}}
        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl bg-ink p-5 text-paper shadow-sm">
                <p class="font-mono text-xs font-semibold uppercase tracking-widest text-paper/50">Available now</p>
                <p class="mt-2 font-mono text-3xl font-bold tracking-tight text-saffron"><x-money :paisa="$available"/></p>
                <p class="mt-1 text-xs text-paper/50">Balance minus open payout holds</p>
            </div>
            <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-sm">
                <p class="font-mono text-xs font-semibold uppercase tracking-widest text-ink/50">Ledger balance</p>
                <p class="mt-2 font-mono text-3xl font-bold tracking-tight text-ink"><x-money :paisa="$balance"/></p>
            </div>
            <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-sm">
                <p class="font-mono text-xs font-semibold uppercase tracking-widest text-ink/50">Lifetime credits</p>
                <p class="mt-2 font-mono text-3xl font-bold tracking-tight text-emerald-700"><x-money :paisa="$lifetimeCredits"/></p>
            </div>
            <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-sm">
                <p class="font-mono text-xs font-semibold uppercase tracking-widest text-ink/50">Sales</p>
                <p class="mt-2 font-mono text-3xl font-bold tracking-tight text-ink">{{ number_format($salesCount) }}</p>
                <p class="mt-1 text-xs text-ink/50">Paid order lines — comps never counted</p>
            </div>
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-2">
            {{-- Payout request --}}
            <section class="rounded-2xl border border-ink/10 bg-white p-6 shadow-sm">
                <h2 class="text-sm font-semibold text-ink">Request a payout</h2>
                <p class="mt-1 text-xs text-ink/60">
                    Minimum <x-money :paisa="$minPayoutPaisa"/>. The requested amount is held immediately
                    (a negative ledger row) until an admin settles it or you cancel.
                </p>

                <form method="POST" action="{{ route('dashboard.earnings.request') }}" class="mt-4 space-y-4">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="amount_npr" class="block text-xs font-medium text-ink/60">Amount (NPR)</label>
                            <input type="number" id="amount_npr" name="amount_npr" min="{{ intdiv($minPayoutPaisa, 100) }}"
                                   value="{{ max(intdiv($minPayoutPaisa, 100), intdiv(max(0, $available), 100)) }}"
                                   class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink outline-none focus:border-saffron-deep">
                        </div>
                        <div>
                            <label for="method" class="block text-xs font-medium text-ink/60">Method</label>
                            <select id="method" name="method"
                                    class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink outline-none focus:border-saffron-deep">
                                <option value="esewa_wallet">eSewa wallet</option>
                                <option value="bank">Bank transfer</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label for="destination" class="block text-xs font-medium text-ink/60">Destination (eSewa number or bank account)</label>
                        <input type="text" id="destination" name="destination" required minlength="4" maxlength="190"
                               class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink outline-none focus:border-saffron-deep">
                        <p class="mt-1 text-xs text-ink/50">Stored encrypted; visible to staff only for settlement.</p>
                    </div>
                    <button class="rounded-xl bg-saffron px-4 py-2.5 text-sm font-bold text-ink transition hover:bg-saffron-deep"
                            @disabled($available < $minPayoutPaisa)>Request payout</button>
                    @if ($available < $minPayoutPaisa)
                        <p class="text-xs text-ink/50">Available balance is below the <x-money :paisa="$minPayoutPaisa"/> minimum.</p>
                    @endif
                </form>
            </section>

            {{-- Payout history --}}
            <section class="rounded-2xl border border-ink/10 bg-white p-6 shadow-sm">
                <h2 class="text-sm font-semibold text-ink">Payout history</h2>
                @if ($payouts->isEmpty())
                    <p class="mt-3 rounded-xl border border-dashed border-ink/20 p-6 text-center text-sm text-ink/50">No payouts requested yet.</p>
                @else
                    <ul class="mt-3 divide-y divide-ink/10">
                        @foreach ($payouts as $payout)
                            <li class="flex items-center justify-between gap-3 py-3">
                                <div>
                                    <p class="font-mono text-sm font-bold text-ink"><x-money :paisa="$payout->amount_paisa"/></p>
                                    <p class="text-xs text-ink/50">{{ $payout->requested_at->format('M j, Y') }} · {{ str_replace('_', ' ', $payout->method) }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="rounded-full px-2.5 py-0.5 font-mono text-[11px] font-bold
                                        {{ $payout->status === 'settled' ? 'bg-emerald-100 text-emerald-800' : '' }}
                                        {{ $payout->status === 'approved' ? 'bg-sky-100 text-sky-800' : '' }}
                                        {{ $payout->status === 'requested' ? 'bg-amber-100 text-amber-800' : '' }}
                                        {{ in_array($payout->status, ['rejected', 'cancelled'], true) ? 'bg-rose-100 text-rose-700' : '' }}">
                                        {{ $payout->status }}
                                    </span>
                                    @if ($payout->status === 'requested')
                                        <form method="POST" action="{{ route('dashboard.earnings.cancel', $payout) }}"
                                              onsubmit="return confirm('Cancel this payout? The held amount returns to your available balance.')">
                                            @csrf
                                            <button class="rounded-lg border border-ink/10 px-2.5 py-1 text-xs font-semibold text-ink/70 transition hover:border-rose-500 hover:text-rose-700">Cancel</button>
                                        </form>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        {{-- Recent ledger rows --}}
        <section class="mt-6 overflow-hidden rounded-2xl border border-ink/10 bg-white shadow-sm">
            <div class="border-b border-ink/10 px-6 py-4">
                <h2 class="text-sm font-semibold text-ink">Ledger (recent rows)</h2>
            </div>
            @if ($ledger->isEmpty())
                <p class="px-6 py-10 text-center text-sm text-ink/50">No ledger rows yet — your first sale credits this table.</p>
            @else
                <table class="min-w-full divide-y divide-ink/10 text-sm">
                    <thead class="bg-paper-deep text-left text-xs uppercase tracking-wider text-ink0">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Date</th>
                            <th class="px-6 py-3 font-semibold">Type</th>
                            <th class="px-6 py-3 text-right font-semibold">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink/10">
                        @foreach ($ledger as $row)
                            <tr>
                                <td class="px-6 py-3 font-mono text-xs text-ink/60">{{ $row->created_at?->format('M j, Y H:i') }}</td>
                                <td class="px-6 py-3 font-mono text-xs text-ink/80">{{ $row->type }}</td>
                                <td class="px-6 py-3 text-right font-mono text-sm font-bold {{ $row->amount_paisa >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                    <x-money :paisa="$row->amount_paisa" :signed="true"/>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    </div>
</x-app-layout>
