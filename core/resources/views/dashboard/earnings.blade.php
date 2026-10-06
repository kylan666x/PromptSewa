<x-app-layout>
    <x-seo title="Earnings" robots="noindex, follow"/>

    @php
        /** @var int $salesCount */
        /** @var int $sikkaSpendable */
        /** @var int $sikkaCashoutable */
        /** @var int $sikkaMin */
        /** @var \Illuminate\Support\Collection<int, \App\Models\SikkaTransaction> $sikkaLedger */
        /** @var \Illuminate\Support\Collection<int, \App\Models\Payout> $sikkaPayouts */
    @endphp

    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="font-mono text-xs font-semibold uppercase tracking-[0.2em] text-saffron-deep">Creator earnings</p>
                <h1 class="mt-1 text-3xl font-bold tracking-tight text-ink">Your Sikka</h1>
                <p class="mt-1 text-sm text-ink/60">Every credit is a row in an insert-only ledger — the numbers below are its sum.</p>
            </div>
            <a href="{{ route('dashboard') }}"
               class="rounded-full border border-ink/15 px-4 py-2 text-sm font-semibold text-ink/70 transition hover:border-ink/30">&larr; Back to dashboard</a>
        </header>

        {{-- S1 (v1.9.0): the tab is Sikka-only — spendable, cash-out eligible
             and the sales count. The legacy NPR wallet cards are retired. --}}
        <div class="mt-8 grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl bg-ink p-5 text-paper shadow-sm">
                <p class="font-mono text-xs font-semibold uppercase tracking-widest text-paper/50">Sikka spendable</p>
                <p class="mt-2 text-3xl font-bold tracking-tight text-saffron"><x-sikka :amount="$sikkaSpendable" :word="true" :size="24"/></p>
                <p class="mt-1 text-xs text-paper/50">Every credit, including the ones still being earned — spend them on prompts.</p>
            </div>
            <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-sm">
                <p class="font-mono text-xs font-semibold uppercase tracking-widest text-ink/50">Cash-out eligible</p>
                <p class="mt-2 text-3xl font-bold tracking-tight text-ink"><x-sikka :amount="$sikkaCashoutable" :size="24"/></p>
                <p class="mt-1 text-xs text-ink/50">Top-ups, sale credits and stipends — engagement rewards are spend-only.</p>
            </div>
            <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-sm">
                <p class="font-mono text-xs font-semibold uppercase tracking-widest text-ink/50">Sales</p>
                <p class="mt-2 font-mono text-3xl font-bold tracking-tight text-ink">{{ number_format($salesCount) }}</p>
                <p class="mt-1 text-xs text-ink/50">Paid order lines — comps never counted</p>
            </div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            {{-- Withdraw earnings — the UI speaks Sikka; settlement happens in
                 the backend and is never echoed here. --}}
            <section class="rounded-2xl border border-ink/10 bg-white p-6 shadow-sm">
                <h2 class="text-sm font-semibold text-ink">Withdraw earnings</h2>
                <p class="mt-1 text-xs text-ink/60">
                    Minimum <x-sikka :amount="$sikkaMin"/> credits. The requested credits are held immediately
                    (a negative ledger row) until an admin settles the withdrawal or you cancel.
                </p>

                <form method="POST" action="{{ route('dashboard.earnings.sikka.request') }}" class="mt-4 space-y-4">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="amount_sikka" class="block text-xs font-medium text-ink/60">Credits</label>
                            <input type="number" id="amount_sikka" name="amount_sikka" min="{{ max(1, $sikkaMin) }}" max="{{ max(1, $sikkaCashoutable) }}"
                                   value="{{ max($sikkaMin, $sikkaCashoutable) }}"
                                   class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink outline-none focus:border-saffron-deep">
                        </div>
                        <div>
                            <label for="sikka_method" class="block text-xs font-medium text-ink/60">Payout method</label>
                            <select id="sikka_method" name="method"
                                    class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink outline-none focus:border-saffron-deep">
                                <option value="esewa_wallet">eSewa wallet</option>
                                <option value="bank">Bank transfer</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label for="sikka_destination" class="block text-xs font-medium text-ink/60">Destination (eSewa number or bank account)</label>
                        <input type="text" id="sikka_destination" name="destination" required minlength="4" maxlength="190"
                               class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink outline-none focus:border-saffron-deep">
                        <p class="mt-1 text-xs text-ink/50">Stored encrypted; visible to staff only for settlement.</p>
                    </div>
                    <button class="rounded-xl bg-saffron px-4 py-2.5 text-sm font-bold text-ink transition hover:bg-saffron-deep"
                            @disabled($sikkaCashoutable < $sikkaMin)>Withdraw earnings</button>
                    @if ($sikkaCashoutable < $sikkaMin)
                        <p class="text-xs text-ink/50">Cash-out eligible balance is below the <x-sikka :amount="$sikkaMin"/> minimum.</p>
                    @endif
                </form>
            </section>

            {{-- Sikka withdrawals — status only. The NPR settlement figure is
                 backend data and is never rendered on this page. --}}
            <section class="rounded-2xl border border-ink/10 bg-white p-6 shadow-sm">
                <h2 class="text-sm font-semibold text-ink">Withdrawal requests</h2>
                @if ($sikkaPayouts->isEmpty())
                    <p class="mt-3 rounded-xl border border-dashed border-ink/20 p-6 text-center text-sm text-ink/50">No withdrawals requested yet.</p>
                @else
                    <ul class="mt-3 divide-y divide-ink/10">
                        @foreach ($sikkaPayouts as $payout)
                            <li class="flex items-center justify-between gap-3 py-3">
                                <div>
                                    <p class="text-sm font-bold text-ink"><x-sikka :amount="$payout->sikka_amount"/></p>
                                    <p class="text-xs text-ink/50">
                                        {{ $payout->requested_at->format('M j, Y') }} · {{ str_replace('_', ' ', $payout->method) }}
                                    </p>
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
                                        <form method="POST" action="{{ route('dashboard.earnings.sikka.cancel', $payout) }}"
                                              onsubmit="return confirm('Cancel this withdrawal? The held credits return to your Sikka balance.')">
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

        {{-- Sikka ledger browser --}}
        <section class="mt-6 overflow-hidden rounded-2xl border border-ink/10 bg-white shadow-sm">
            <div class="border-b border-ink/10 px-6 py-4">
                <h2 class="text-sm font-semibold text-ink">Sikka credits ledger (recent rows)</h2>
                <p class="mt-0.5 text-xs text-ink/50">Insert-only: every movement is a new row, and balances are its sum.</p>
            </div>
            @if ($sikkaLedger->isEmpty())
                <p class="px-6 py-10 text-center text-sm text-ink/50">No Sikka credits yet — a top-up, a sale or a membership stipend starts the ledger.</p>
            @else
                <table class="min-w-full divide-y divide-ink/10 text-sm">
                    <thead class="bg-paper-deep text-left text-xs uppercase tracking-wider text-ink/50">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Date</th>
                            <th class="px-6 py-3 font-semibold">Type</th>
                            <th class="px-6 py-3 font-semibold">Eligibility</th>
                            <th class="px-6 py-3 text-right font-semibold">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink/10">
                        @foreach ($sikkaLedger as $row)
                            <tr>
                                <td class="px-6 py-3 font-mono text-xs text-ink/60">{{ $row->created_at?->format('M j, Y H:i') }}</td>
                                <td class="px-6 py-3 font-mono text-xs text-ink/80">{{ str_replace('_', ' ', $row->type) }}</td>
                                <td class="px-6 py-3">
                                    @if ($row->cashout_eligible)
                                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 font-mono text-[11px] font-bold text-emerald-800">cash-out</span>
                                    @else
                                        <span class="rounded-full bg-ink/10 px-2 py-0.5 font-mono text-[11px] font-bold text-ink/60">spend only</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-right {{ $row->amount_sikka >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                    <x-sikka :amount="$row->amount_sikka"/>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    </div>
</x-app-layout>
