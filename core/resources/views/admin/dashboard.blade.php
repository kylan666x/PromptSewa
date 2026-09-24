<x-admin-layout title="Overview">
    @php
        /** @var array<string, int> $stats */
        /** @var \Illuminate\Support\Collection<int, \App\Models\Prompt> $reviewQueue */
        /** @var \Illuminate\Support\Collection<int, \App\Models\Order> $manualQueue */
    @endphp

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @foreach ([
            'Prompts' => [$stats['prompts'], 'text-ink'],
            'Published' => [$stats['published'], 'text-emerald-800'],
            'Awaiting review' => [$stats['pending'], 'text-saffron-deep'],
            'Users' => [$stats['users'], 'text-sky-800'],
            'Orders' => [$stats['orders'], 'text-violet-300'],
            'Revenue (paid)' => ['Rs. '.number_format(intdiv($stats['revenue_paisa'], 100)), 'text-emerald-800'],
            'Open reports' => [$stats['open_reports'], $stats['open_reports'] > 0 ? 'text-rose-700' : 'text-ink0'],
        ] as $label => [$value, $color])
            <div class="rounded-2xl border border-ink/10 bg-white p-4">
                <p class="text-[11px] font-semibold uppercase tracking-widest text-ink0">{{ $label }}</p>
                <p class="mt-1.5 text-2xl font-bold tracking-tight {{ $color }}">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl border border-ink/10 bg-white p-5">
            <h3 class="text-sm font-semibold text-ink">Review queue
                <span class="ml-1 rounded-full bg-saffron/25 px-2 py-0.5 text-xs font-medium text-saffron-deep">{{ $stats['pending'] }} pending</span>
            </h3>
            @if ($reviewQueue->isEmpty())
                <p class="mt-4 text-sm text-ink0">Queue is clear — nothing awaiting review. 🎉</p>
            @else
                <ul class="mt-4 space-y-3">
                    @foreach ($reviewQueue as $item)
                        <li class="flex items-center justify-between gap-3 text-sm">
                            <div class="min-w-0">
                                <p class="truncate font-medium text-ink">{{ $item->title }}</p>
                                <p class="text-xs text-ink0">{{ $item->creator?->name ?? 'Unknown' }} · {{ $item->category?->name ?? 'Uncategorized' }}</p>
                            </div>
                            <a href="{{ route('dashboard.prompts.edit', $item) }}"
                               class="shrink-0 rounded-lg border border-ink/10 px-2.5 py-1 text-xs text-ink/80 transition hover:border-saffron-deep hover:text-saffron-deep">Review</a>
                        </li>
                    @endforeach
                </ul>
            @endif
            <a href="{{ route('admin.prompts.index') }}" class="mt-4 inline-block text-xs font-medium text-saffron-deep hover:text-saffron-deep">Open full moderation →</a>
        </div>

        <div class="rounded-2xl border border-ink/10 bg-white p-5">
            <h3 class="text-sm font-semibold text-ink">Manual payments to verify</h3>
            @if ($manualQueue->isEmpty())
                <p class="mt-4 text-sm text-ink0">No manual payments waiting.</p>
            @else
                <ul class="mt-4 space-y-3">
                    @foreach ($manualQueue as $order)
                        <li class="flex items-center justify-between gap-3 text-sm">
                            <div class="min-w-0">
                                <p class="font-medium text-ink">Order #{{ $order->id }} — {{ $order->priceLabel ?? 'Rs. '.number_format(intdiv($order->total_paisa, 100)) }}</p>
                                <p class="truncate text-xs text-ink0">{{ $order->buyer?->name ?? 'Unknown' }} · ref: {{ Str::limit($order->payment_reference, 40) }}</p>
                            </div>
                            <a href="{{ route('admin.orders.index', ['status' => 'pending']) }}"
                               class="shrink-0 rounded-lg border border-ink/10 px-2.5 py-1 text-xs text-ink/80 transition hover:border-saffron-deep hover:text-saffron-deep">Verify</a>
                        </li>
                    @endforeach
                </ul>
            @endif
            <a href="{{ route('admin.orders.index') }}" class="mt-4 inline-block text-xs font-medium text-saffron-deep hover:text-saffron-deep">All orders →</a>
        </div>
    </div>

    <div class="mt-6">
        <a href="{{ route('admin.reports.index', ['status' => \App\Models\PromptReport::STATUS_OPEN]) }}"
           class="flex items-center justify-between rounded-2xl border p-5 transition {{ $stats['open_reports'] > 0 ? 'border-rose-700/30 bg-rose-50 hover:border-rose-700/60' : 'border-ink/10 bg-white hover:border-ink/25' }}">
            <div>
                <p class="text-sm font-semibold text-ink">Abuse reports</p>
                <p class="mt-0.5 text-xs text-ink0">Review "Report this prompt" submissions from the community.</p>
            </div>
            <span class="font-mono text-sm {{ $stats['open_reports'] > 0 ? 'font-bold text-rose-700' : 'text-ink0' }}">
                {{ $stats['open_reports'] }} open →
            </span>
        </a>
    </div>
</x-admin-layout>
