<x-admin-layout title="Orders">
    @php
        /** @var \Illuminate\Pagination\LengthAwarePaginator $orders */
    @endphp

    <div class="flex flex-wrap gap-2">
        @foreach ([
            '' => 'All',
            \App\Models\Order::STATUS_PENDING => 'Pending',
            \App\Models\Order::STATUS_PAID => 'Paid',
            \App\Models\Order::STATUS_FAILED => 'Failed',
            \App\Models\Order::STATUS_REFUNDED => 'Refunded',
        ] as $statusKey => $label)
            <a href="{{ route('admin.orders.index', $statusKey === '' ? [] : ['status' => $statusKey]) }}"
               @class([
                   'rounded-xl border px-3.5 py-2 text-sm font-medium transition',
                   'border-saffron-deep bg-saffron/20 text-saffron-deep' => $currentStatus === $statusKey,
                   'border-ink/10 bg-paper-deep text-ink/80 hover:border-ink/25' => $currentStatus !== $statusKey,
               ])>{{ $label }}</a>
        @endforeach
    </div>

    <div class="mt-6 overflow-x-auto rounded-2xl border border-ink/10">
        <table class="min-w-full divide-y divide-ink/10 text-sm">
            <thead class="bg-paper-deep text-left text-xs uppercase tracking-wider text-ink0">
                <tr>
                    <th class="px-5 py-3 font-semibold">Order</th>
                    <th class="px-5 py-3 font-semibold">Buyer</th>
                    <th class="hidden px-5 py-3 font-semibold md:table-cell">Contents</th>
                    <th class="px-5 py-3 font-semibold">Total</th>
                    <th class="hidden px-5 py-3 font-semibold md:table-cell">Method / reference</th>
                    <th class="px-5 py-3 font-semibold">Status</th>
                    <th class="px-5 py-3 text-right font-semibold">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-ink/10 bg-white">
                @forelse ($orders as $order)
                    <tr class="transition hover:bg-paper-deep">
                        <td class="px-5 py-3.5">
                            <p class="font-medium text-ink">#{{ $order->id }}</p>
                            <p class="text-xs text-ink0">{{ $order->created_at->format('M j, Y H:i') }}</p>
                        </td>
                        <td class="px-5 py-3.5 text-ink/60">{{ $order->buyer?->name ?? '—' }}</td>
                        <td class="hidden px-5 py-3.5 text-ink/60 md:table-cell">
                            {{ $order->items->map(fn ($item) => $item->pack?->name ?? $item->product?->prompt?->title ?? 'Item')->implode(', ') }}
                        </td>
                        <td class="px-5 py-3.5 font-medium text-ink">Rs. {{ number_format(intdiv($order->total_paisa, 100)) }}</td>
                        <td class="hidden px-5 py-3.5 text-ink/60 md:table-cell">
                            <p>{{ ucfirst($order->payment_method ?? '—') }}</p>
                            @if ($order->payment_reference)
                                <p class="max-w-[200px] truncate text-xs text-ink0" title="{{ $order->payment_reference }}">{{ $order->payment_reference }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="rounded-md px-2 py-0.5 text-xs font-medium
                                {{ match ($order->status) {
                                    \App\Models\Order::STATUS_PAID => 'bg-emerald-100 text-emerald-800',
                                    \App\Models\Order::STATUS_PENDING => 'bg-saffron/25 text-saffron-deep',
                                    \App\Models\Order::STATUS_FAILED => 'bg-rose-100 text-rose-700',
                                    default => 'bg-paper-deep text-ink/80',
                                } }}">
                                {{ $order->status }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5">
                            @if ($order->isPending())
                                <div class="flex items-center justify-end gap-1.5">
                                    <form method="POST" action="{{ route('admin.orders.approve', $order) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="rounded-lg border border-emerald-700/40 bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-800 transition hover:bg-emerald-600/20">Approve & grant</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.orders.reject', $order) }}" onsubmit="return confirm('Reject this order?')">
                                        @csrf
                                        @method('PATCH')
                                        <button class="rounded-lg border border-rose-700/30 bg-rose-100 px-2.5 py-1 text-xs font-medium text-rose-700 transition hover:bg-rose-200">Reject</button>
                                    </form>
                                </div>
                            @else
                                <span class="text-xs text-ink/40">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-10 text-center text-ink0">No orders in this state.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $orders->links() }}</div>
</x-admin-layout>
