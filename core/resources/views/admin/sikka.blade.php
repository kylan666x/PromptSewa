@php
    /** @var bool $enabled */
    /** @var int $buyRate */
    /** @var int $cashoutRate */
    /** @var array{daily:int, publish:int, rating:int, cap:int} $engage */
    /** @var int $cashoutMin */
    /** @var \Illuminate\Support\Collection<int, \App\Models\SikkaPack> $packs */
    /** @var \Illuminate\Support\Collection<int, \App\Models\MembershipPlan> $plans */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Badge> $badges */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Frame> $frames */
    /** @var \Illuminate\Support\Collection<int, \App\Models\User> $users */
    /** @var \Illuminate\Pagination\LengthAwarePaginator $ledger */
    /** @var array $filters */
    /** @var array{circulation:int, cashoutable:int, spent:int} $stats */
@endphp

<x-admin-layout title="Sikka desk">
    <div class="flex flex-wrap items-center gap-2">
        <span class="rounded-full border px-3 py-1 font-mono text-xs font-bold {{ $enabled ? 'border-emerald-500/40 bg-emerald-50 text-emerald-800' : 'border-ink/15 bg-paper-deep text-ink/60' }}">
            {{ $enabled ? 'Kill-switch ON — the economy is live' : 'Kill-switch OFF — zero Sikka markup everywhere' }}
        </span>
        <span class="rounded-full border border-ink/15 bg-white px-3 py-1 font-mono text-xs text-ink/60">
            1 credit = <x-money :paisa="$buyRate"/> to buy · <x-money :paisa="$cashoutRate"/> to cash out
        </span>
    </div>

    {{-- Economy totals, derived from the insert-only ledger --}}
    <div class="mt-5 grid gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-ink/10 bg-white p-5">
            <p class="font-mono text-xs font-semibold uppercase tracking-widest text-ink/50">In circulation</p>
            <p class="mt-2 text-2xl font-bold text-ink"><x-sikka :amount="$stats['circulation']" :word="true"/></p>
            <p class="mt-1 text-xs text-ink/50">SUM(amount_sikka) — every row, positive and negative</p>
        </div>
        <div class="rounded-2xl border border-ink/10 bg-white p-5">
            <p class="font-mono text-xs font-semibold uppercase tracking-widest text-ink/50">Cash-out eligible</p>
            <p class="mt-2 text-2xl font-bold text-ink"><x-sikka :amount="$stats['cashoutable']"/></p>
        </div>
        <div class="rounded-2xl border border-ink/10 bg-white p-5">
            <p class="font-mono text-xs font-semibold uppercase tracking-widest text-ink/50">Spent on prompts</p>
            <p class="mt-2 text-2xl font-bold text-ink"><x-sikka :amount="$stats['spent']"/></p>
        </div>
    </div>

    {{-- Rates + bounds --}}
    <form method="POST" action="{{ route('admin.sikka.settings') }}" class="mt-6 rounded-2xl border border-ink/10 bg-white p-6">
        @csrf
        @method('PUT')
        <h2 class="text-sm font-semibold text-ink">Rates, bounds &amp; engagement</h2>
        <p class="mt-1 text-xs text-ink/60">
            Buy rate 50–500 paisa per credit; cash-out rate 10 … the buy rate (the spread can never invert).
            Engagement amounts are capped per user per day.
        </p>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <label class="flex items-center gap-2 rounded-xl border border-ink/10 bg-paper-deep px-3 py-2.5 text-sm text-ink/80">
                <input type="checkbox" name="sikka_enabled" value="1" @checked($enabled) class="size-4 rounded border-ink/20 text-saffron-deep">
                Sikka enabled (kill-switch)
            </label>
            <div>
                <label for="sikka_buy_paisa_per_token" class="block text-xs font-medium text-ink/60">Buy rate (paisa / credit)</label>
                <input id="sikka_buy_paisa_per_token" type="number" name="sikka_buy_paisa_per_token" min="50" max="500" required value="{{ $buyRate }}"
                       class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink">
            </div>
            <div>
                <label for="sikka_cashout_paisa_per_token" class="block text-xs font-medium text-ink/60">Cash-out rate (paisa / credit)</label>
                <input id="sikka_cashout_paisa_per_token" type="number" name="sikka_cashout_paisa_per_token" min="10" max="{{ $buyRate }}" required value="{{ $cashoutRate }}"
                       class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink">
            </div>
            <div>
                <label for="sikka_cashout_min" class="block text-xs font-medium text-ink/60">Cash-out minimum (credits)</label>
                <input id="sikka_cashout_min" type="number" name="sikka_cashout_min" min="0" max="1000000" required value="{{ $cashoutMin }}"
                       class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink">
            </div>
            <div>
                <label for="engage_daily_sikka" class="block text-xs font-medium text-ink/60">Daily visit</label>
                <input id="engage_daily_sikka" type="number" name="engage_daily_sikka" min="0" max="50" required value="{{ $engage['daily'] }}"
                       class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink">
            </div>
            <div>
                <label for="engage_publish_sikka" class="block text-xs font-medium text-ink/60">Publish</label>
                <input id="engage_publish_sikka" type="number" name="engage_publish_sikka" min="0" max="50" required value="{{ $engage['publish'] }}"
                       class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink">
            </div>
            <div>
                <label for="engage_rating_sikka" class="block text-xs font-medium text-ink/60">Rating received</label>
                <input id="engage_rating_sikka" type="number" name="engage_rating_sikka" min="0" max="50" required value="{{ $engage['rating'] }}"
                       class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink">
            </div>
            <div>
                <label for="engage_daily_cap_sikka" class="block text-xs font-medium text-ink/60">Daily engagement cap</label>
                <input id="engage_daily_cap_sikka" type="number" name="engage_daily_cap_sikka" min="0" max="500" required value="{{ $engage['cap'] }}"
                       class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink">
            </div>
        </div>

        <button class="mt-4 rounded-xl bg-saffron px-4 py-2.5 text-sm font-bold text-ink hover:bg-saffron-deep">Save Sikka settings</button>
    </form>

    {{-- Packs --}}
    <section class="mt-6 rounded-2xl border border-ink/10 bg-white p-6">
        <h2 class="text-sm font-semibold text-ink">Top-up packs</h2>
        <p class="mt-1 text-xs text-ink/60">Sold through the NPR rails; the credits land on payment approval (double approval credits once).</p>

        <form method="POST" action="{{ route('admin.sikka.packs.store') }}" class="mt-4 grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
            @csrf
            <input type="text" name="name" required maxlength="80" placeholder="Name" class="rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink">
            <input type="text" name="slug" required maxlength="80" pattern="[a-z0-9-]+" placeholder="slug" class="rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink">
            <input type="number" name="sikka_amount" required min="1" max="1000000" placeholder="Credits" class="rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink">
            <input type="number" name="bonus_sikka" required min="0" max="1000000" value="0" placeholder="Bonus" class="rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink">
            <input type="number" name="price_paisa" required min="0" max="100000000" placeholder="Price (paisa)" class="rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink">
            <button class="rounded-xl bg-saffron px-4 py-2 text-sm font-bold text-ink hover:bg-saffron-deep">Add pack</button>
        </form>

        <ul class="mt-4 divide-y divide-ink/10">
            @forelse ($packs as $pack)
                <li class="py-3">
                    <form method="POST" action="{{ route('admin.sikka.packs.update', $pack) }}" class="grid items-center gap-3 sm:grid-cols-3 lg:grid-cols-6">
                        @csrf
                        @method('PUT')
                        <input type="text" name="name" required maxlength="80" value="{{ $pack->name }}" class="rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink">
                        <input type="text" name="slug" required maxlength="80" pattern="[a-z0-9-]+" value="{{ $pack->slug }}" class="rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink">
                        <input type="number" name="sikka_amount" required min="1" max="1000000" value="{{ $pack->sikka_amount }}" class="rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink">
                        <input type="number" name="bonus_sikka" required min="0" max="1000000" value="{{ $pack->bonus_sikka }}" class="rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink">
                        <input type="number" name="price_paisa" required min="0" max="100000000" value="{{ $pack->price_paisa }}" class="rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink">
                        <div class="flex items-center gap-2">
                            <label class="flex items-center gap-1.5 text-xs text-ink/70"><input type="checkbox" name="active" value="1" @checked($pack->active) class="size-4 rounded border-ink/20 text-saffron-deep">active</label>
                            <button class="rounded-lg bg-ink px-3 py-1.5 text-xs font-bold text-paper hover:bg-ink-soft">Save</button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('admin.sikka.packs.destroy', $pack) }}" class="mt-1 flex items-center justify-between"
                          onsubmit="return confirm('Delete this pack? Sold packs deactivate instead.')">
                        @csrf
                        @method('DELETE')
                        <span class="text-xs text-ink/50">
                            <x-sikka :amount="$pack->sikka_amount"/> + <x-sikka :amount="$pack->bonus_sikka"/> bonus · <x-money :paisa="$pack->price_paisa"/>
                        </span>
                        <button class="rounded-lg border border-ink/10 px-2.5 py-1 text-xs font-semibold text-ink/70 hover:border-rose-500 hover:text-rose-700">Delete</button>
                    </form>
                </li>
            @empty
                <li class="py-4 text-sm text-ink/50">No packs yet.</li>
            @endforelse
        </ul>
    </section>

    {{-- Plans --}}
    <section class="mt-6 rounded-2xl border border-ink/10 bg-white p-6">
        <h2 class="text-sm font-semibold text-ink">Membership plans</h2>
        <p class="mt-1 text-xs text-ink/60">NPR-rail plans; activation grants the first stipend and every perk through the existing choke points.</p>

        <form method="POST" action="{{ route('admin.sikka.plans.store') }}" class="mt-4 grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
            @csrf
            <input type="text" name="name" required maxlength="80" placeholder="Name" class="rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink">
            <input type="text" name="slug" required maxlength="80" pattern="[a-z0-9-]+" placeholder="slug" class="rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink">
            <input type="number" name="duration_days" required min="1" max="3650" placeholder="Days" class="rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink">
            <input type="number" name="price_paisa" required min="0" max="100000000" placeholder="Price (paisa)" class="rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink">
            <input type="number" name="stipend_sikka" required min="0" max="100000" value="0" placeholder="Stipend" class="rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink">
            <select name="badge_id" class="rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink">
                <option value="">No badge perk</option>
                @foreach ($badges as $badge)
                    <option value="{{ $badge->id }}">{{ $badge->name }}</option>
                @endforeach
            </select>
            <select name="frame_id" class="rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink">
                <option value="">No frame perk</option>
                @foreach ($frames as $frame)
                    <option value="{{ $frame->id }}">{{ $frame->name }}</option>
                @endforeach
            </select>
            <label class="flex items-center gap-1.5 text-xs text-ink/70"><input type="checkbox" name="unlimited_unlock" value="1" class="size-4 rounded border-ink/20 text-saffron-deep">unlimited unlock</label>
            <label class="flex items-center gap-1.5 text-xs text-ink/70"><input type="checkbox" name="grant_verified" value="1" class="size-4 rounded border-ink/20 text-saffron-deep">verified</label>
            <label class="flex items-center gap-1.5 text-xs text-ink/70"><input type="checkbox" name="active" value="1" checked class="size-4 rounded border-ink/20 text-saffron-deep">active</label>
            <button class="rounded-xl bg-saffron px-4 py-2 text-sm font-bold text-ink hover:bg-saffron-deep">Add plan</button>
        </form>

        <ul class="mt-4 divide-y divide-ink/10">
            @forelse ($plans as $plan)
                <li class="py-3">
                    <form method="POST" action="{{ route('admin.sikka.plans.update', $plan) }}" class="grid items-center gap-3 sm:grid-cols-3 lg:grid-cols-6">
                        @csrf
                        @method('PUT')
                        <input type="text" name="name" required maxlength="80" value="{{ $plan->name }}" class="rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink">
                        <input type="text" name="slug" required maxlength="80" pattern="[a-z0-9-]+" value="{{ $plan->slug }}" class="rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink">
                        <input type="number" name="duration_days" required min="1" max="3650" value="{{ $plan->duration_days }}" class="rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink">
                        <input type="number" name="price_paisa" required min="0" max="100000000" value="{{ $plan->price_paisa }}" class="rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink">
                        <input type="number" name="stipend_sikka" required min="0" max="100000" value="{{ $plan->stipend_sikka }}" class="rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink">
                        <div class="flex flex-wrap items-center gap-2">
                            <select name="badge_id" class="rounded-lg border border-ink/10 bg-paper-deep px-2 py-1.5 text-xs text-ink">
                                <option value="">No badge</option>
                                @foreach ($badges as $badge)
                                    <option value="{{ $badge->id }}" @selected((int) $plan->perk(\App\Models\MembershipPlan::PERK_BADGE_ID) === $badge->id)>{{ $badge->name }}</option>
                                @endforeach
                            </select>
                            <select name="frame_id" class="rounded-lg border border-ink/10 bg-paper-deep px-2 py-1.5 text-xs text-ink">
                                <option value="">No frame</option>
                                @foreach ($frames as $frame)
                                    <option value="{{ $frame->id }}" @selected((int) $plan->perk(\App\Models\MembershipPlan::PERK_FRAME_ID) === $frame->id)>{{ $frame->name }}</option>
                                @endforeach
                            </select>
                            <label class="flex items-center gap-1 text-xs text-ink/70"><input type="checkbox" name="unlimited_unlock" value="1" @checked($plan->hasUnlimitedUnlock()) class="size-4 rounded border-ink/20 text-saffron-deep">unlimited</label>
                            <label class="flex items-center gap-1 text-xs text-ink/70"><input type="checkbox" name="grant_verified" value="1" @checked((bool) $plan->perk(\App\Models\MembershipPlan::PERK_GRANT_VERIFIED)) class="size-4 rounded border-ink/20 text-saffron-deep">verified</label>
                            <label class="flex items-center gap-1 text-xs text-ink/70"><input type="checkbox" name="active" value="1" @checked($plan->active) class="size-4 rounded border-ink/20 text-saffron-deep">active</label>
                            <button class="rounded-lg bg-ink px-3 py-1.5 text-xs font-bold text-paper hover:bg-ink-soft">Save</button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('admin.sikka.plans.destroy', $plan) }}" class="mt-1 flex items-center justify-between"
                          onsubmit="return confirm('Delete this plan? Plans with members deactivate instead.')">
                        @csrf
                        @method('DELETE')
                        <span class="text-xs text-ink/50">
                            {{ $plan->duration_days }} days · <x-money :paisa="$plan->price_paisa"/> · stipend <x-sikka :amount="$plan->stipend_sikka"/>
                            @if ($plan->hasUnlimitedUnlock()) · unlimited unlock @endif
                        </span>
                        <button class="rounded-lg border border-ink/10 px-2.5 py-1 text-xs font-semibold text-ink/70 hover:border-rose-500 hover:text-rose-700">Delete</button>
                    </form>
                </li>
            @empty
                <li class="py-4 text-sm text-ink/50">No plans yet.</li>
            @endforelse
        </ul>
    </section>

    {{-- Grants --}}
    <section class="mt-6 rounded-2xl border border-ink/10 bg-white p-6">
        <h2 class="text-sm font-semibold text-ink">Grant Sikka credits</h2>
        <p class="mt-1 text-xs text-ink/60">
            Issued as an admin_grant row — spend-only by default. Ticking the cash-out flip makes THIS grant
            withdrawable; eligibility is decided at write time (the ledger is insert-only), the mandatory
            reason lands in the row's meta and the admin log.
        </p>

        <form method="POST" action="{{ route('admin.sikka.grants.store') }}" class="mt-4 grid gap-3 sm:grid-cols-3 lg:grid-cols-5">
            @csrf
            <select name="user_id" required class="rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink">
                <option value="">Choose a user…</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }} — {{ $user->email }}</option>
                @endforeach
            </select>
            <input type="number" name="amount_sikka" required min="1" max="1000000" placeholder="Credits" class="rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink">
            <input type="text" name="reason" required minlength="3" maxlength="255" placeholder="Reason (mandatory, audited)" class="rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink">
            <label class="flex items-center gap-1.5 text-xs text-ink/70">
                <input type="checkbox" name="cashout_eligible" value="1" class="size-4 rounded border-ink/20 text-saffron-deep">
                Flip to cash-out eligible
            </label>
            <button class="rounded-xl bg-saffron px-4 py-2 text-sm font-bold text-ink hover:bg-saffron-deep">Grant credits</button>
        </form>
    </section>

    {{-- Ledger browser --}}
    <section class="mt-6 rounded-2xl border border-ink/10 bg-white p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-ink">Sikka ledger browser</h2>
            <form method="GET" action="{{ route('admin.sikka.index') }}" class="flex flex-wrap items-center gap-2">
                <input type="number" name="user" value="{{ $filters['user'] ?? '' }}" placeholder="user id"
                       class="w-24 rounded-xl border border-ink/10 bg-paper-deep px-3 py-1.5 text-xs text-ink">
                <select name="type" class="rounded-xl border border-ink/10 bg-paper-deep px-3 py-1.5 text-xs text-ink/90">
                    <option value="">All types</option>
                    @foreach (\App\Models\SikkaTransaction::TYPES as $type)
                        <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
                <select name="eligibility" class="rounded-xl border border-ink/10 bg-paper-deep px-3 py-1.5 text-xs text-ink/90">
                    <option value="">Any eligibility</option>
                    <option value="cashout" @selected(($filters['eligibility'] ?? '') === 'cashout')>Cash-out eligible</option>
                    <option value="spend" @selected(($filters['eligibility'] ?? '') === 'spend')>Spend only</option>
                </select>
                <button class="rounded-xl bg-saffron px-3 py-1.5 text-xs font-bold text-ink hover:bg-saffron-deep">Filter</button>
            </form>
        </div>

        @if ($ledger->isEmpty())
            <p class="mt-4 rounded-xl border border-dashed border-ink/20 p-8 text-center text-sm text-ink/50">No ledger rows match.</p>
        @else
            <table class="mt-4 min-w-full divide-y divide-ink/10 text-sm">
                <thead class="bg-paper-deep text-left text-xs uppercase tracking-wider text-ink/50">
                    <tr>
                        <th class="px-4 py-2.5 font-semibold">Date</th>
                        <th class="px-4 py-2.5 font-semibold">User</th>
                        <th class="px-4 py-2.5 font-semibold">Type</th>
                        <th class="px-4 py-2.5 font-semibold">Eligibility</th>
                        <th class="px-4 py-2.5 text-right font-semibold">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink/10">
                    @foreach ($ledger as $row)
                        <tr>
                            <td class="px-4 py-2.5 font-mono text-xs text-ink/60">{{ $row->created_at?->format('M j, Y H:i') }}</td>
                            <td class="px-4 py-2.5 text-xs text-ink/80">{{ $row->user?->name ?? ('user '.$row->user_id) }}</td>
                            <td class="px-4 py-2.5 font-mono text-xs text-ink/80">{{ str_replace('_', ' ', $row->type) }}</td>
                            <td class="px-4 py-2.5">
                                @if ($row->cashout_eligible)
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 font-mono text-[11px] font-bold text-emerald-800">cash-out</span>
                                @else
                                    <span class="rounded-full bg-ink/10 px-2 py-0.5 font-mono text-[11px] font-bold text-ink/60">spend only</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-right {{ $row->amount_sikka >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                <x-sikka :amount="$row->amount_sikka"/>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="mt-4">{{ $ledger->links() }}</div>
        @endif
    </section>
</x-admin-layout>
