<x-app-layout>
    @php
        /** @var \App\Models\Order $order */
        /** @var bool $esewaEnabled */
        /** @var bool $manualEnabled */
        /** @var string $manualInstructions */
        $isPaid = $order->isPaid();
    @endphp

    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <h1 class="text-3xl font-bold tracking-tight text-ink">Checkout</h1>
        <p class="mt-1 font-mono text-sm text-ink/50">Order #{{ $order->id }} · placed {{ $order->created_at->format('M j, Y') }}</p>

        <div class="mt-8 rounded-2xl border border-ink/10 bg-white p-6 shadow-sm">
            <h2 class="font-mono text-xs font-semibold uppercase tracking-wider text-ink/50">Order summary</h2>
            <ul class="mt-4 divide-y divide-ink/10">
                @foreach ($order->items as $item)
                    <li class="flex items-center justify-between gap-4 py-3 text-sm">
                        <div class="min-w-0">
                            <p class="font-medium text-ink">
                                @if ($item->pack)
                                    {{ $item->pack->name }}
                                    <span class="font-mono text-xs text-ink/50">(bundle)</span>
                                @else
                                    {{ $item->product?->prompt?->title ?? 'Prompt' }}
                                @endif
                            </p>
                            <p class="font-mono text-xs text-ink/50">Qty {{ $item->quantity }}</p>
                        </div>
                        <span class="shrink-0 font-mono font-medium text-ink">Rs. {{ number_format(intdiv($item->lineTotalPaisa(), 100)) }}</span>
                    </li>
                @endforeach
            </ul>
            <div class="mt-3 flex items-center justify-between border-t border-ink/10 pt-4">
                <span class="text-sm text-ink/60">Total</span>
                <span class="font-mono text-2xl font-bold tracking-tight text-ink">Rs. {{ number_format(intdiv($order->total_paisa, 100)) }}</span>
            </div>
        </div>

        @if ($isPaid)
            <div class="mt-6 rounded-2xl border border-emerald-700/20 bg-emerald-50 p-6 text-center">
                <p class="text-sm font-semibold text-emerald-800">✓ This order is paid — your prompts are unlocked.</p>
                <a href="{{ route('purchases.index') }}" class="mt-3 inline-block rounded-full bg-ink px-5 py-2.5 text-sm font-semibold text-paper transition hover:bg-ink-soft">Open my library</a>
            </div>
        @elseif ($order->payment_method === 'manual' && $order->payment_reference)
            <div class="mt-6 rounded-2xl border border-saffron-deep/30 bg-saffron/15 p-6 text-center">
                <p class="text-sm font-semibold text-ink">Payment reference received — an admin will verify it shortly.</p>
                <p class="mt-1 font-mono text-xs text-ink/60">Reference: {{ $order->payment_reference }}</p>
                <a href="{{ route('purchases.index') }}" class="mt-3 inline-block font-mono text-xs font-semibold text-ink underline decoration-saffron decoration-2 underline-offset-4 hover:text-saffron-deep">Go to my library →</a>
            </div>
        @else
            <div class="mt-6 space-y-5">
                @if ($esewaEnabled)
                    <div class="rounded-2xl border border-ink/10 bg-white p-6 shadow-sm">
                        <h3 class="flex items-center gap-2 text-sm font-semibold text-ink">
                            <span class="rounded-md bg-emerald-100 px-2 py-0.5 font-mono text-xs font-bold text-emerald-800">eSewa</span>
                            Pay online
                        </h3>
                        <p class="mt-2 text-xs leading-relaxed text-ink/60">You'll be redirected to eSewa's secure checkout and returned here automatically.</p>
                        <a href="{{ route('checkout.esewa.pay', $order) }}"
                           class="mt-4 flex w-full items-center justify-center rounded-full bg-emerald-600 px-4 py-3 text-sm font-bold text-white transition hover:bg-emerald-600">
                            Pay with eSewa — Rs. {{ number_format(intdiv($order->total_paisa, 100)) }}
                        </a>
                    </div>
                @endif

                @if ($manualEnabled)
                    <div class="rounded-2xl border border-ink/10 bg-white p-6 shadow-sm">
                        <h3 class="flex items-center gap-2 text-sm font-semibold text-ink">
                            <span class="rounded-md bg-sky-100 px-2 py-0.5 font-mono text-xs font-bold text-sky-800">Manual</span>
                            Bank / wallet transfer
                        </h3>
                        @if ($manualInstructions !== '')
                            <p class="mt-2 whitespace-pre-line text-xs leading-relaxed text-ink/60">{{ $manualInstructions }}</p>
                        @endif
                        <form method="POST" action="{{ route('checkout.manual.submit', $order) }}" class="mt-4 space-y-3">
                            @csrf
                            <div>
                                <label for="payment_reference" class="block text-xs font-medium text-ink/70">Transaction ID / reference <span class="text-saffron-deep">*</span></label>
                                <input id="payment_reference" type="text" name="payment_reference" required minlength="4" maxlength="180"
                                       placeholder="e.g. 9F3K2L8Q or bank slip number"
                                       class="mt-1.5 block w-full rounded-xl border border-ink/15 bg-white px-3.5 py-2.5 text-sm text-ink placeholder-creak shadow-sm outline-none transition focus:border-saffron-deep focus:ring-2 focus:ring-saffron/40">
                                @error('payment_reference') <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            <button class="w-full rounded-full border border-sky-700/30 bg-sky-100 px-4 py-3 text-sm font-bold text-sky-900 transition hover:bg-sky-200">
                                Submit payment reference
                            </button>
                            <p class="text-center text-xs text-ink/50">An admin verifies it manually — usually within a few hours.</p>
                        </form>
                    </div>
                @endif

                @if (! $esewaEnabled && ! $manualEnabled)
                    <div class="rounded-2xl border border-saffron-deep/30 bg-saffron/10 p-6 text-center">
                        <p class="text-sm font-semibold text-ink">Checkout is being configured.</p>
                        <p class="mt-1 text-xs text-ink/60">No payment methods are enabled yet — check back soon.</p>
                    </div>
                @endif
            </div>
        @endif

        <a href="{{ route('library.index') }}" class="mt-8 inline-block text-sm font-medium text-ink/50 transition hover:text-ink">← Keep browsing</a>
    </div>
</x-app-layout>
