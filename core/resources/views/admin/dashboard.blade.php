<x-admin-layout title="Overview">
    @php
        /** @var array<string, int> $stats */
        /** @var \Illuminate\Support\Collection<int, \App\Models\Prompt> $reviewQueue */
        /** @var \Illuminate\Support\Collection<int, \App\Models\Order> $manualQueue */
        /** @var array $revenueSeries */
        /** @var array $ordersSeries */
        /** @var array $usersSeries */
        /** @var array $reportsSeries */
    @endphp

    {{-- A1: running release chip — value lives in config/app.php 'version' --}}
    <p class="mb-4 inline-flex items-center gap-2 rounded-full border border-ink/10 bg-white px-3 py-1 font-mono text-xs text-ink/60 shadow-sm">
        <span class="size-2 rounded-full bg-emerald-500" aria-hidden="true"></span>
        v{{ config('app.version') }}
    </p>

    {{-- G5 (v1.7.0): 30-day series — same sparkline component as creator Stats --}}
    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-ink/10 bg-white p-4">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-ink/60">Revenue · 30d</p>
            {{-- P4 (v1.7.1): honest sublabel — this series is PAID ORDERS
                 (orders table), which includes pre-ledger revenue. The
                 ledger-based truth lives on the Finance desk. --}}
            <x-sparkline :series="$revenueSeries" label="Revenue over the last 30 days (paisa)"/>
            <p class="mt-1 font-mono text-xs text-ink/50">paid orders · incl. pre-ledger · <x-money :paisa="array_sum($revenueSeries)"/></p>
        </div>
        <div class="rounded-2xl border border-ink/10 bg-white p-4">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-ink/60">Orders · 30d</p>
            <x-sparkline :series="$ordersSeries" label="Paid orders over the last 30 days" stroke="#059669" fill="rgba(5, 150, 105, 0.12)"/>
            <p class="mt-1 font-mono text-xs text-ink/50">{{ number_format(array_sum($ordersSeries)) }} paid</p>
        </div>
        <div class="rounded-2xl border border-ink/10 bg-white p-4">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-ink/60">New users · 30d</p>
            <x-sparkline :series="$usersSeries" label="New users over the last 30 days" stroke="#2563eb" fill="rgba(37, 99, 235, 0.10)"/>
            <p class="mt-1 font-mono text-xs text-ink/50">{{ number_format(array_sum($usersSeries)) }} joined</p>
        </div>
        <div class="rounded-2xl border border-ink/10 bg-white p-4">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-ink/60">Reports · 30d</p>
            <x-sparkline :series="$reportsSeries" label="Reports filed over the last 30 days" stroke="#dc2626" fill="rgba(220, 38, 38, 0.10)"/>
            <p class="mt-1 font-mono text-xs text-ink/50">{{ number_format(array_sum($reportsSeries)) }} filed</p>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @foreach ([
            'Prompts' => [$stats['prompts'], 'text-ink'],
            'Published' => [$stats['published'], 'text-emerald-800'],
            'Awaiting review' => [$stats['pending'], 'text-saffron-deep'],
            'Users' => [$stats['users'], 'text-sky-800'],
            'Orders' => [$stats['orders'], 'text-violet-300'],
            'Revenue (paid)' => ['Rs. '.number_format(intdiv($stats['revenue_paisa'], 100)), 'text-emerald-800'],
            'Open reports' => [$stats['open_reports'], $stats['open_reports'] > 0 ? 'text-rose-700' : 'text-ink/60'],
        ] as $label => [$value, $color])
            <div class="rounded-2xl border border-ink/10 bg-white p-4">
                <p class="text-[11px] font-semibold uppercase tracking-widest text-ink/60">{{ $label }}</p>
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
                <p class="mt-4 text-sm text-ink/60">Queue is clear — nothing awaiting review. 🎉</p>
            @else
                <ul class="mt-4 space-y-3">
                    @foreach ($reviewQueue as $item)
                        <li class="flex items-center justify-between gap-3 text-sm">
                            <div class="min-w-0">
                                <p class="truncate font-medium text-ink">{{ $item->title }}</p>
                                <p class="text-xs text-ink/60">{{ $item->creator?->name ?? 'Unknown' }} · {{ $item->category?->name ?? 'Uncategorized' }}</p>
                            </div>
                            {{-- B6: staff edit via dashboard route leaks the owner path; moderation goes through the admin preview route. --}}
                            <a href="{{ route('admin.prompts.preview', $item) }}"
                               class="shrink-0 rounded-lg border border-ink/10 px-2.5 py-1 text-xs text-ink/80 transition hover:border-saffron-deep hover:text-saffron-deep">Review</a>
                        </li>
                    @endforeach
                </ul>
            @endif
            <a href="{{ route('admin.prompts.index') }}" class="mt-4 inline-block text-xs font-medium text-saffron-deep hover:text-saffron-deep">Open full moderation &rarr;</a>
        </div>

        <div class="rounded-2xl border border-ink/10 bg-white p-5">
            <h3 class="text-sm font-semibold text-ink">Manual payments to verify</h3>
            @if ($manualQueue->isEmpty())
                <p class="mt-4 text-sm text-ink/60">No manual payments waiting.</p>
            @else
                <ul class="mt-4 space-y-3">
                    @foreach ($manualQueue as $order)
                        <li class="flex items-center justify-between gap-3 text-sm">
                            <div class="min-w-0">
                                <p class="font-medium text-ink">Order #{{ $order->id }} — {{ $order->priceLabel ?? 'Rs. '.number_format(intdiv($order->total_paisa, 100)) }}</p>
                                <p class="truncate text-xs text-ink/60">{{ $order->buyer?->name ?? 'Unknown' }} · ref: {{ Str::limit($order->payment_reference, 40) }}</p>
                            </div>
                            <a href="{{ route('admin.orders.index', ['status' => 'pending']) }}"
                               class="shrink-0 rounded-lg border border-ink/10 px-2.5 py-1 text-xs text-ink/80 transition hover:border-saffron-deep hover:text-saffron-deep">Verify</a>
                        </li>
                    @endforeach
                </ul>
            @endif
            <a href="{{ route('admin.orders.index') }}" class="mt-4 inline-block text-xs font-medium text-saffron-deep hover:text-saffron-deep">All orders &rarr;</a>
        </div>
    </div>

    <div class="mt-6">
        <a href="{{ route('admin.reports.index', ['status' => \App\Models\PromptReport::STATUS_OPEN]) }}"
           class="flex items-center justify-between rounded-2xl border p-5 transition {{ $stats['open_reports'] > 0 ? 'border-rose-700/30 bg-rose-50 hover:border-rose-700/60' : 'border-ink/10 bg-white hover:border-ink/25' }}">
            <div>
                <p class="text-sm font-semibold text-ink">Abuse reports</p>
                <p class="mt-0.5 text-xs text-ink/60">Review "Report this prompt" submissions from the community.</p>
            </div>
            <span class="font-mono text-sm {{ $stats['open_reports'] > 0 ? 'font-bold text-rose-700' : 'text-ink/60' }}">
                {{ $stats['open_reports'] }} open &rarr;
            </span>
        </a>
    </div>
</x-admin-layout>
