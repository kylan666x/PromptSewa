<x-admin-layout title="Payment methods">
    @php
        /** @var bool $esewaEnabled */
        /** @var string $esewaMerchantCode */
        /** @var string $esewaBaseUrl */
        /** @var bool $esewaSecretSaved */
        /** @var bool $manualEnabled */
        /** @var string $manualInstructions */
        /** @var bool $paymentsEnabled */
    @endphp

    <form method="POST" action="{{ route('admin.payments.update') }}" class="max-w-3xl space-y-6">
        @csrf
        @method('PUT')

        <div class="rounded-2xl border border-ink/10 bg-white p-6">
            <label class="flex items-center gap-3">
                <input type="hidden" name="payments_enabled" value="0">
                <input type="checkbox" name="payments_enabled" value="1" @checked($paymentsEnabled)
                       class="size-4 rounded border-ink/20 bg-paper-deep text-saffron-deep focus:ring-saffron/40">
                <span class="text-sm font-semibold text-ink">Enable checkout (buy buttons go live)</span>
            </label>
        </div>

        <div class="rounded-2xl border border-ink/10 bg-white p-6">
            <h3 class="flex items-center gap-2 text-sm font-semibold text-ink">
                <span class="rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-800">eSewa</span>
                Online payment gateway
            </h3>
            <label class="mt-4 flex items-center gap-3">
                <input type="hidden" name="esewa_enabled" value="0">
                <input type="checkbox" name="esewa_enabled" value="1" @checked($esewaEnabled)
                       class="size-4 rounded border-ink/20 bg-paper-deep text-saffron-deep focus:ring-saffron/40">
                <span class="text-sm text-ink/80">Enable eSewa checkout</span>
            </label>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-ink/60">Merchant code (ESEWA_MERCHANT_CODE)</label>
                    <input type="text" name="esewa_merchant_code" value="{{ $esewaMerchantCode }}" placeholder="e.g. EPAYTEST"
                           class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink outline-none focus:border-saffron-deep">
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink/60">Gateway base URL</label>
                    <input type="url" name="esewa_base_url" value="{{ $esewaBaseUrl }}"
                           class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink outline-none focus:border-saffron-deep">
                    <p class="mt-1 text-xs text-ink0">Production: <code class="text-ink/60">https://epay.esewa.com.np</code> · Test: <code class="text-ink/60">https://rc.esewa.com.np</code></p>
                </div>
            </div>

            <div class="mt-4">
                <label class="block text-xs font-medium text-ink/60">
                    Merchant secret key
                    @if ($esewaSecretSaved)
                        <span class="ml-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800">✓ saved (encrypted)</span>
                    @endif
                </label>
                <input type="password" name="esewa_secret_key" placeholder="{{ $esewaSecretSaved ? 'Leave empty to keep the saved secret' : 'Paste your eSewa HMAC secret' }}"
                       class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink outline-none focus:border-saffron-deep">
                <p class="mt-1 text-xs text-ink0">Stored encrypted with the app key — never shown again, never logged.</p>
            </div>
        </div>

        <div class="rounded-2xl border border-ink/10 bg-white p-6">
            <h3 class="flex items-center gap-2 text-sm font-semibold text-ink">
                <span class="rounded-md bg-sky-100 px-2 py-0.5 text-xs font-bold text-sky-800">Manual</span>
                Bank / wallet transfer with manual verification
            </h3>
            <label class="mt-4 flex items-center gap-3">
                <input type="hidden" name="manual_enabled" value="0">
                <input type="checkbox" name="manual_enabled" value="1" @checked($manualEnabled)
                       class="size-4 rounded border-ink/20 bg-paper-deep text-saffron-deep focus:ring-saffron/40">
                <span class="text-sm text-ink/80">Accept manual payments (admin approves each order)</span>
            </label>

            <div class="mt-4">
                <label class="block text-xs font-medium text-ink/60">Instructions shown at checkout</label>
                <textarea name="manual_payment_instructions" rows="4"
                          class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink outline-none focus:border-saffron-deep"
                          placeholder="e.g. Send Rs. 199 to eSewa 98XXXXXXXX, then paste the transaction ID below.">{{ $manualInstructions }}</textarea>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button class="rounded-xl bg-saffron px-5 py-2.5 text-sm font-semibold text-ink transition hover:bg-saffron-deep">Save payment settings</button>
            <span class="text-xs text-ink0">Changes apply immediately.</span>
        </div>
    </form>
</x-admin-layout>
