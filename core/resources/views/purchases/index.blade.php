<x-app-layout>
    <x-seo :title="'My purchases'" robots="noindex, follow"/>
    @php
        /** @var \Illuminate\Support\Collection<int, \App\Models\LicenseGrant> $grants */
        /** @var \Illuminate\Support\Collection<int, \App\Models\Order> $orders */
    @endphp

    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6">
        <header>
            <h1 class="text-3xl font-bold tracking-tight text-ink">My purchases</h1>
            <p class="mt-2 text-sm text-ink/60">Every prompt and pack you own — unlocks forever, including future versions.</p>
        </header>

        {{-- ===== T13 (v1.5.0): order -> items -> license state, pack rows expand ===== --}}
        <section class="mt-8" aria-label="Orders and licenses">
            <h2 class="font-mono text-xs font-semibold uppercase tracking-wider text-ink/50">Orders</h2>

            @if ($orders->isEmpty())
                <div class="mt-4 rounded-2xl border border-dashed border-ink/20 bg-white p-10 text-center">
                    <p class="text-sm text-ink/60">No purchases yet.</p>
                    <a href="{{ route('library.index') }}" class="mt-3 inline-block rounded-full bg-saffron px-4 py-2 text-sm font-bold text-ink transition hover:bg-saffron-deep">Browse the library</a>
                </div>
            @else
                <div class="mt-4 space-y-4">
                    @foreach ($orders as $order)
                        <details class="group rounded-2xl border border-ink/10 bg-white shadow-sm" @if ($loop->first) open @endif>
                            <summary class="flex cursor-pointer flex-wrap items-center justify-between gap-3 px-5 py-4">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="font-mono text-sm font-bold text-ink">#{{ $order->id }}</span>
                                    <span class="font-mono text-xs text-ink/50">{{ $order->created_at->format('M j, Y') }}</span>
                                    <span class="rounded-md px-2 py-0.5 font-mono text-xs font-medium
                                        {{ match ($order->status) {
                                            \App\Models\Order::STATUS_PAID => 'bg-emerald-100 text-emerald-800',
                                            \App\Models\Order::STATUS_PENDING => 'bg-saffron/25 text-ink',
                                            \App\Models\Order::STATUS_FAILED => 'bg-rose-100 text-rose-700',
                                            default => 'bg-paper-deep text-ink/60',
                                        } }}">
                                        {{ $order->status }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="font-mono text-sm text-ink">Rs. {{ number_format(intdiv($order->total_paisa, 100)) }}</span>
                                    <span class="font-mono text-xs text-ink/40 transition group-open:rotate-90">▸</span>
                                </div>
                            </summary>

                            <div class="border-t border-ink/10 px-5 py-4">
                                @forelse ($order->items as $item)
                                    @php
                                        $pack = $item->pack;
                                        $itemGrant = $item->licenseGrant;
                                    @endphp
                                    <div class="rounded-xl border border-ink/10 bg-paper-deep/60 p-4">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <p class="text-sm font-semibold text-ink">
                                                @if ($pack)
                                                    📦 {{ $pack->name }} <span class="font-mono text-xs text-ink/50">(pack)</span>
                                                @else
                                                    {{ $item->product?->prompt?->title ?? 'Prompt' }}
                                                @endif
                                            </p>
                                            <span class="rounded-md px-2 py-0.5 font-mono text-[10px] font-bold
                                                {{ ($itemGrant?->status ?? ($pack ? 'active' : '')) === \App\Models\LicenseGrant::STATUS_ACTIVE ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-700' }}">
                                                {{ $pack ? 'BUNDLE' : ($itemGrant?->status ?? 'no grant') }}
                                            </span>
                                        </div>

                                        {{-- Pack rows expand to their granted prompts --}}
                                        @if ($pack)
                                            <ul class="mt-3 space-y-1.5">
                                                @php
                                                    $packGrants = $grants->where('order_item_id', $item->id);
                                                @endphp
                                                @forelse ($packGrants as $packGrant)
                                                    @continue($packGrant->prompt === null)
                                                    <li class="flex items-center justify-between gap-3 rounded-lg bg-white px-3 py-2">
                                                        <a href="{{ route('prompts.show', $packGrant->prompt) }}" class="min-w-0 truncate text-sm text-ink/80 hover:text-saffron-deep">{{ $packGrant->prompt->title }}</a>
                                                        <span class="flex shrink-0 items-center gap-2">
                                                            <span class="rounded px-1.5 py-0.5 font-mono text-[10px] font-bold {{ $packGrant->status === \App\Models\LicenseGrant::STATUS_ACTIVE ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-700' }}">{{ $packGrant->status }}</span>
                                                            @if ($packGrant->status === \App\Models\LicenseGrant::STATUS_ACTIVE)
                                                                <a href="{{ route('purchases.download', $packGrant->prompt) }}" class="rounded-full border border-ink/15 px-2.5 py-1 font-mono text-[11px] font-semibold text-ink/70 transition hover:border-saffron-deep hover:text-saffron-deep">⬇ Re-download</a>
                                                            @endif
                                                        </span>
                                                    </li>
                                                @empty
                                                    <li class="rounded-lg bg-white px-3 py-2 text-xs text-ink/50">Grants appear here once the order is fulfilled.</li>
                                                @endforelse
                                            </ul>
                                        @else
                                            <div class="mt-3 flex items-center justify-between gap-3">
                                                <span class="font-mono text-[11px] text-ink/50">{{ ucfirst($item->product?->prompt?->license_tier ?? 'personal') }} license</span>
                                                @if ($itemGrant?->status === \App\Models\LicenseGrant::STATUS_ACTIVE && $item->prompt_id !== null && \App\Models\Prompt::query()->find($item->prompt_id))
                                                    <a href="{{ route('purchases.download', \App\Models\Prompt::find($item->prompt_id)) }}" class="rounded-full border border-ink/15 px-3 py-1.5 font-mono text-xs font-semibold text-ink/70 transition hover:border-saffron-deep hover:text-saffron-deep">⬇ Re-download</a>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-sm text-ink/50">This order has no items.</p>
                                @endforelse
                            </div>
                        </details>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- ===== Owned prompts (flat grant list) ===== --}}
        <section class="mt-12" aria-label="Owned prompts">
            <h2 class="font-mono text-xs font-semibold uppercase tracking-wider text-ink/50">All owned prompts</h2>
            @php
                $activeGrants = $grants->filter(fn ($g) => $g->status === \App\Models\LicenseGrant::STATUS_ACTIVE && $g->prompt !== null);
            @endphp
            @if ($activeGrants->isEmpty())
                <div class="mt-4 rounded-2xl border border-dashed border-ink/20 bg-white p-10 text-center">
                    <p class="text-sm text-ink/60">No active licenses yet.</p>
                </div>
            @else
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($activeGrants as $grant)
                        <div class="flex flex-col rounded-2xl border border-ink/10 bg-white p-5 shadow-sm">
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="font-semibold leading-snug text-ink">
                                    <a href="{{ route('prompts.show', $grant->prompt) }}" class="hover:text-saffron-deep">{{ $grant->prompt->title }}</a>
                                </h3>
                                <span class="shrink-0 rounded-md bg-emerald-100 px-2 py-0.5 font-mono text-[10px] font-bold text-emerald-800">ACTIVE</span>
                            </div>
                            {{-- W1 (v1.7.3): purchases rows carry the creator frame. --}}
                            @if ($grant->prompt->creator)
                                <div class="mt-2 flex items-center gap-2 text-xs text-ink/50">
                                    <x-user-avatar :user="$grant->prompt->creator" size="xs" :frame="$grant->prompt->creator->activeFrame"/>
                                    <x-user-handle :user="$grant->prompt->creator" size="text-xs"/>
                                </div>
                            @endif
                            <p class="mt-2 line-clamp-2 text-xs leading-relaxed text-ink/50">{{ $grant->prompt->description }}</p>
                            <div class="mt-auto flex items-center justify-between pt-4">
                                <span class="font-mono text-[11px] text-ink/50">{{ ucfirst($grant->license_tier) }} license</span>
                                <a href="{{ route('purchases.download', $grant->prompt) }}" class="rounded-full border border-ink/15 px-3 py-1.5 font-mono text-xs font-semibold text-ink/70 transition hover:border-saffron-deep hover:text-saffron-deep">⬇ Download</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="mt-12" aria-label="Saved prompts">
            <h2 class="font-mono text-xs font-semibold uppercase tracking-wider text-ink/50">Saved</h2>
            <p class="mt-2 text-sm text-ink/60">Bookmarked prompts live on your <a href="{{ route('dashboard', ['tab' => 'saved']) }}" class="font-semibold text-ink underline decoration-saffron decoration-2 underline-offset-4 hover:text-saffron-deep">dashboard Saved tab</a>.</p>
        </section>
    </div>
</x-app-layout>
