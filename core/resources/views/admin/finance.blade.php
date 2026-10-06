<x-admin-layout title="Finance">
    @php
        /** @var int $grossPaisa */
        /** @var int $creatorCreditsPaisa */
        /** @var int $platformNetPaisa */
        /** @var \Illuminate\Support\Carbon $ledgerStartedAt */
        /** @var \Illuminate\Support\Collection<int, \App\Models\Order> $preLedgerOrders */
        /** @var int $preLedgerTotalPaisa */
        /** @var \Illuminate\Support\Collection<int, \App\Models\Payout> $payoutQueue */
        /** @var \Illuminate\Pagination\LengthAwarePaginator $ledger */
        /** @var array $filters */
    @endphp

    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-bold tracking-tight text-ink">Finance desk <span class="ml-1 rounded-full border border-ink/15 bg-white px-2.5 py-0.5 align-middle font-mono text-[11px] font-semibold text-ink/60">ledger gross · post-cutover</span></h2>
            {{-- P4 (v1.7.1): the chip above keeps the two truths distinct —
                 this desk reads the LEDGER (post-cutover), while the admin
                 Overview sparkline reads paid orders (incl. pre-ledger). --}}
            <p class="mt-1 text-xs text-ink/60">Ledger cutover: {{ $ledgerStartedAt->format('M j, Y H:i') }} — totals below start there.</p>
        </div>
    </div>

    {{-- Totals --}}
    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-ink/10 bg-white p-5">
            <p class="font-mono text-xs font-semibold uppercase tracking-widest text-ink/50">Gross paid (post-ledger)</p>
            <p class="mt-2 font-mono text-3xl font-bold text-ink"><x-money :paisa="$grossPaisa"/></p>
        </div>
        <div class="rounded-2xl border border-ink/10 bg-white p-5">
            <p class="font-mono text-xs font-semibold uppercase tracking-widest text-ink/50">Creator credits</p>
            <p class="mt-2 font-mono text-3xl font-bold text-emerald-700"><x-money :paisa="$creatorCreditsPaisa"/></p>
            <p class="mt-1 text-xs text-ink/50">SUM(sale_credit) — from the ledger</p>
        </div>
        <div class="rounded-2xl border border-ink/10 bg-white p-5">
            <p class="font-mono text-xs font-semibold uppercase tracking-widest text-ink/50">Platform net (derived)</p>
            <p class="mt-2 font-mono text-3xl font-bold text-saffron-deep"><x-money :paisa="$platformNetPaisa"/></p>
            <p class="mt-1 text-xs text-ink/50">gross − credits — never stored</p>
        </div>
    </div>

    {{-- Pre-ledger revenue --}}
    <div class="mt-6 rounded-2xl border border-amber-500/30 bg-amber-50/60 p-6">
        <h3 class="text-sm font-semibold text-amber-800">Pre-ledger revenue</h3>
        <p class="mt-1 text-xs text-amber-700/90">
            Paid orders before the ledger cutover. <strong>No wallet rows by design</strong> — this
            revenue is historical truth, not a creator balance. Never backfilled.
        </p>
        @if ($preLedgerOrders->isEmpty())
            <p class="mt-3 text-xs text-amber-700/70">No pre-ledger orders.</p>
        @else
            <p class="mt-3 font-mono text-sm font-bold text-amber-900">Total: <x-money :paisa="$preLedgerTotalPaisa"/> · {{ $preLedgerOrders->count() }} order(s)</p>
            <ul class="mt-2 divide-y divide-amber-500/20">
                @foreach ($preLedgerOrders as $order)
                    <li class="flex items-center justify-between py-1.5 text-xs text-amber-900">
                        <span class="font-mono">#{{ $order->id }} · {{ $order->paid_at?->format('M j, Y') }} · {{ $order->buyer?->name ?? '—' }}</span>
                        <span class="font-mono font-bold"><x-money :paisa="$order->total_paisa"/></span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Payout queue --}}
    <div class="mt-6 rounded-2xl border border-ink/10 bg-white p-6">
        <h3 class="text-sm font-semibold text-ink">Payout queue</h3>
        @if ($payoutQueue->isEmpty())
            <p class="mt-3 text-sm text-ink/50">Queue clear — no open payout requests.</p>
        @else
            <ul class="mt-3 divide-y divide-ink/10">
                @foreach ($payoutQueue as $payout)
                    <li class="py-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="text-sm font-semibold text-ink">
                                    #{{ $payout->id }} · {{ $payout->user?->name ?? ('user '.$payout->user_id) }}
                                    @if ($payout->isSikkaSource())
                                        <span class="ml-1 rounded-full bg-ink px-2 py-0.5 font-mono text-[10px] font-bold text-saffron">SIKKA</span>
                                        — <x-sikka :amount="$payout->sikka_amount"/>
                                        @if ($payout->settled_npr_paisa !== null)
                                            <span class="text-xs text-ink/60">&rarr; <x-money :paisa="$payout->settled_npr_paisa"/></span>
                                        @endif
                                    @else
                                        <span class="ml-1 rounded-full bg-ink/10 px-2 py-0.5 font-mono text-[10px] font-bold text-ink/60">NPR</span>
                                        — <span class="font-mono"><x-money :paisa="$payout->amount_paisa"/></span>
                                    @endif
                                </p>
                                <p class="text-xs text-ink/50">
                                    {{ str_replace('_', ' ', $payout->method) }} · requested {{ $payout->requested_at->format('M j, Y H:i') }}
                                    @if ($payout->isSikkaSource() && $payout->status === 'approved')
                                        · settles at <x-money :paisa="$sikkaCashoutRate"/> per credit
                                    @elseif ($payout->status === 'approved')
                                        · awaiting settlement
                                    @endif
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" data-reveal-payout="{{ $payout->id }}"
                                        class="rounded-lg border border-ink/10 px-2.5 py-1 text-xs font-semibold text-ink/70 hover:border-sky-500 hover:text-sky-700">Reveal destination</button>
                                <span class="rounded-full px-2 py-0.5 font-mono text-[10px] font-bold {{ $payout->status === 'requested' ? 'bg-amber-100 text-amber-800' : 'bg-sky-100 text-sky-800' }}">{{ $payout->status }}</span>
                                @if ($payout->status === 'requested')
                                    <form method="POST" action="{{ route('admin.finance.payouts.approve', $payout) }}">
                                        @csrf
                                        <button class="rounded-lg bg-sky-600 px-2.5 py-1 text-xs font-bold text-white hover:bg-sky-700">Approve</button>
                                    </form>
                                @endif
                                @if ($payout->status === 'approved')
                                    <form method="POST" action="{{ route('admin.finance.payouts.settle', $payout) }}"
                                          onsubmit="return confirm('Mark settled? Confirm ONLY after the transfer succeeded.')">
                                        @csrf
                                        <button class="rounded-lg bg-emerald-600 px-2.5 py-1 text-xs font-bold text-white hover:bg-emerald-700">Mark settled</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('admin.finance.payouts.reject', $payout) }}"
                                      onsubmit="return confirm('Reject this payout? The held amount returns to the creator.')">
                                    @csrf
                                    <button class="rounded-lg bg-rose-600 px-2.5 py-1 text-xs font-bold text-white hover:bg-rose-700">Reject</button>
                                </form>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Ledger browser --}}
    <div class="mt-6 rounded-2xl border border-ink/10 bg-white p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-sm font-semibold text-ink">Ledger browser</h3>
            <form method="GET" action="{{ route('admin.finance') }}" class="flex flex-wrap items-center gap-2">
                <input type="number" name="user" value="{{ $filters['user'] ?? '' }}" placeholder="user id"
                       class="w-24 rounded-xl border border-ink/10 bg-paper-deep px-3 py-1.5 text-xs text-ink">
                <select name="type" class="rounded-xl border border-ink/10 bg-paper-deep px-3 py-1.5 text-xs text-ink/90">
                    <option value="">All types</option>
                    @foreach (\App\Models\WalletTransaction::TYPES as $type)
                        <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="rounded-xl border border-ink/10 bg-paper-deep px-3 py-1.5 text-xs text-ink">
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="rounded-xl border border-ink/10 bg-paper-deep px-3 py-1.5 text-xs text-ink">
                <button class="rounded-xl bg-saffron px-3 py-1.5 text-xs font-bold text-ink hover:bg-saffron-deep">Filter</button>
            </form>
        </div>
        <table class="mt-4 min-w-full divide-y divide-ink/10 text-sm">
            <thead class="bg-paper-deep text-left text-xs uppercase tracking-wider text-ink/60">
                <tr>
                    <th class="px-4 py-2.5 font-semibold">Date</th>
                    <th class="px-4 py-2.5 font-semibold">User</th>
                    <th class="px-4 py-2.5 font-semibold">Type</th>
                    <th class="px-4 py-2.5 text-right font-semibold">Amount</th>
                    <th class="px-4 py-2.5 font-semibold">Key</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-ink/10">
                @forelse ($ledger as $row)
                    <tr>
                        <td class="px-4 py-2.5 font-mono text-xs text-ink/60">{{ $row->created_at?->format('M j, Y H:i') }}</td>
                        <td class="px-4 py-2.5 text-xs text-ink/80">{{ $row->user?->name ?? ('user '.$row->user_id) }}</td>
                        <td class="px-4 py-2.5 font-mono text-xs text-ink/80">{{ $row->type }}</td>
                        <td class="px-4 py-2.5 text-right font-mono text-sm font-bold {{ $row->amount_paisa >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                            <x-money :paisa="$row->amount_paisa" :signed="true"/>
                        </td>
                        <td class="px-4 py-2.5 font-mono text-[10px] text-ink/40">{{ $row->idempotency_key }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-ink/50">No ledger rows match.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-3">{{ $ledger->links() }}</div>
    </div>

    {{-- Destination reveal — staff-gated fetch, no plaintext at rest in DOM until clicked --}}
    @once
        <script>
            document.querySelectorAll('[data-reveal-payout]').forEach((btn) => {
                btn.addEventListener('click', async () => {
                    const res = await fetch(`/admin/finance/payouts/${btn.dataset.revealPayout}/destination`);
                    const data = await res.json();
                    btn.textContent = data.destination ?? data.error ?? 'unavailable';
                    btn.disabled = true;
                });
            });
        </script>
    @endonce
</x-admin-layout>
