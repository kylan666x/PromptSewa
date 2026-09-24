<x-app-layout>
    @php
        /** @var \Illuminate\Pagination\LengthAwarePaginator $grants */
        /** @var \Illuminate\Pagination\LengthAwarePaginator $orders */
    @endphp

    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6">
        <header>
            <h1 class="text-3xl font-bold tracking-tight text-ink">My purchases</h1>
            <p class="mt-2 text-sm text-ink/60">Every prompt and pack you own — unlocks forever, including future versions.</p>
        </header>

        <section class="mt-8" aria-label="Owned prompts">
            <h2 class="font-mono text-xs font-semibold uppercase tracking-wider text-ink/50">Owned prompts</h2>
            @if ($grants->isEmpty())
                <div class="mt-4 rounded-2xl border border-dashed border-ink/20 bg-white p-10 text-center">
                    <p class="text-sm text-ink/60">No purchases yet.</p>
                    <a href="{{ route('library.index') }}" class="mt-3 inline-block rounded-full bg-saffron px-4 py-2 text-sm font-bold text-ink transition hover:bg-saffron-deep">Browse the library</a>
                </div>
            @else
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($grants as $grant)
                        @php $prompt = $grant->prompt; @endphp
                        @continue($prompt === null)
                        <div class="flex flex-col rounded-2xl border border-ink/10 bg-white p-5 shadow-sm">
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="font-semibold leading-snug text-ink">
                                    <a href="{{ route('prompts.show', $prompt) }}" class="hover:text-saffron-deep">{{ $prompt->title }}</a>
                                </h3>
                                <span class="shrink-0 rounded-md bg-emerald-100 px-2 py-0.5 font-mono text-[10px] font-bold text-emerald-800">OWNED</span>
                            </div>
                            <p class="mt-2 line-clamp-2 text-xs leading-relaxed text-ink/50">{{ $prompt->description }}</p>
                            <div class="mt-auto flex items-center justify-between pt-4">
                                <span class="font-mono text-[11px] text-ink/50">{{ ucfirst($grant->license_tier) }} license</span>
                                <a href="{{ route('prompts.show', $prompt) }}" class="rounded-full bg-saffron px-3 py-1.5 text-xs font-bold text-ink transition hover:bg-saffron-deep">Open prompt</a>
                            </div>
                        </div>
                    @endforeach
                </div>
                @if ($grants->hasPages())
                    <div class="mt-6">{{ $grants->links('pagination::tailwind') }}</div>
                @endif
            @endif
        </section>

        <section class="mt-12" aria-label="Order history">
            <h2 class="font-mono text-xs font-semibold uppercase tracking-wider text-ink/50">Order history</h2>
            <div class="mt-4 overflow-hidden rounded-2xl border border-ink/10 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-ink/10 text-sm">
                    <thead class="bg-paper-deep text-left font-mono text-xs uppercase tracking-wider text-ink/50">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Order</th>
                            <th class="hidden px-5 py-3 font-semibold sm:table-cell">Items</th>
                            <th class="px-5 py-3 font-semibold">Total</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 text-right font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink/10">
                        @forelse ($orders as $order)
                            <tr class="transition hover:bg-paper-deep/50">
                                <td class="px-5 py-3.5">
                                    <p class="font-medium text-ink">#{{ $order->id }}</p>
                                    <p class="font-mono text-xs text-ink/50">{{ $order->created_at->format('M j, Y') }}</p>
                                </td>
                                <td class="hidden px-5 py-3.5 text-ink/60 sm:table-cell">
                                    {{ $order->items->map(fn ($item) => $item->pack?->name ?? $item->product?->prompt?->title ?? 'Prompt')->implode(', ') }}
                                </td>
                                <td class="px-5 py-3.5 font-mono text-ink">Rs. {{ number_format(intdiv($order->total_paisa, 100)) }}</td>
                                <td class="px-5 py-3.5">
                                    <span class="rounded-md px-2 py-0.5 font-mono text-xs font-medium
                                        {{ match ($order->status) {
                                            \App\Models\Order::STATUS_PAID => 'bg-emerald-100 text-emerald-800',
                                            \App\Models\Order::STATUS_PENDING => 'bg-saffron/25 text-ink',
                                            \App\Models\Order::STATUS_FAILED => 'bg-rose-100 text-rose-700',
                                            default => 'bg-paper-deep text-ink/60',
                                        } }}">
                                        {{ $order->status }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <a href="{{ route('checkout.show', $order) }}" class="font-mono text-xs font-semibold text-ink underline decoration-saffron decoration-2 underline-offset-4 hover:text-saffron-deep">Details →</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-ink/50">No orders yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($orders->hasPages())
                <div class="mt-6">{{ $orders->links('pagination::tailwind') }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
