<x-admin-layout title="Security">
    @php
        /** @var string $provider */
        /** @var string $siteKey */
        /** @var bool $secretSaved */
        /** @var string $minScore */
        /** @var bool $failOpen */
        /** @var array<int, string> $forms */
        /** @var \Illuminate\Support\Collection<string, bool> $formEnables */
        /** @var string $blockedExtra */
        /** @var int $bundledCount */
        /** @var bool $blockOnReports */
    @endphp

    <h2 class="text-xl font-bold tracking-tight text-ink">Security</h2>
    <p class="mt-1 text-xs text-ink/60">
        Bot challenge (Cloudflare Turnstile / Google reCAPTCHA v3) and the disposable-email blocklist.
        The secret is stored encrypted at rest and never shown again. Fail-closed is the default.
    </p>

    <form method="POST" action="{{ route('admin.security.update') }}" class="mt-6 max-w-3xl space-y-6">
        @csrf
        @method('PUT')

        <div class="rounded-2xl border border-ink/10 bg-white p-6">
            <h3 class="text-sm font-semibold text-ink">Bot challenge</h3>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-ink/60">Provider (empty = disabled everywhere)</label>
                    <select name="captcha_provider" class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink">
                        <option value="" @selected($provider === '')>Disabled</option>
                        <option value="turnstile" @selected($provider === 'turnstile')>Cloudflare Turnstile</option>
                        <option value="recaptcha_v3" @selected($provider === 'recaptcha_v3')>Google reCAPTCHA v3</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink/60">Site key (public)</label>
                    <input type="text" name="captcha_site_key" value="{{ $siteKey }}" placeholder="0x…"
                           class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink">
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink/60">
                        Secret key
                        @if ($secretSaved)
                            <span class="ml-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800">✓ saved (encrypted)</span>
                        @endif
                    </label>
                    <input type="password" name="captcha_secret" placeholder="{{ $secretSaved ? 'Leave empty to keep the saved secret' : 'Paste the provider secret' }}"
                           class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink">
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink/60">Minimum score (reCAPTCHA v3 only)</label>
                    <input type="number" name="captcha_min_score" value="{{ $minScore }}" min="0" max="1" step="0.1"
                           class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink">
                </div>
            </div>

            <label class="mt-4 flex items-center gap-3">
                <input type="hidden" name="captcha_fail_open" value="0">
                <input type="checkbox" name="captcha_fail_open" value="1" @checked($failOpen)
                       class="size-4 rounded border-ink/20 text-saffron-deep">
                <span class="text-sm text-ink/80">Fail OPEN on provider errors <span class="text-xs text-ink/50">(unchecked = fail-closed: provider outage blocks form posts)</span></span>
            </label>

            <div class="mt-4">
                <p class="text-xs font-medium text-ink/60">Enable per form</p>
                <div class="mt-2 flex flex-wrap gap-4">
                    @foreach ($forms as $form)
                        <label class="flex items-center gap-2 text-sm text-ink/80">
                            <input type="checkbox" name="captcha_form_{{ $form }}" value="1" @checked($formEnables->get($form))
                                   class="size-4 rounded border-ink/20 text-saffron-deep">
                            {{ ucfirst($form) }}
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-ink/10 bg-white p-6">
            <h3 class="text-sm font-semibold text-ink">Disposable-email blocklist</h3>
            <p class="mt-1 text-xs text-ink/60">
                {{ $bundledCount }} domains bundled. Add extra domains below (one per line) — matching covers subdomains, case-insensitive.
            </p>
            <textarea name="blocked_domains_extra" rows="5"
                      class="mt-3 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 font-mono text-xs text-ink"
                      placeholder="example-trash-domain.com">{{ $blockedExtra }}</textarea>

            <label class="mt-3 flex items-center gap-3">
                <input type="hidden" name="block_disposable_on_reports" value="0">
                <input type="checkbox" name="block_disposable_on_reports" value="1" @checked($blockOnReports)
                       class="size-4 rounded border-ink/20 text-saffron-deep">
                <span class="text-sm text-ink/80">Also block disposable domains on report contact emails (default off)</span>
            </label>
        </div>

        <div class="flex items-center gap-3">
            <button class="rounded-xl bg-saffron px-5 py-2.5 text-sm font-semibold text-ink transition hover:bg-saffron-deep">Save security settings</button>
        </div>
    </form>

    {{-- T6: test an address against the MERGED list (bundle + extras) --}}
    <div class="mt-8 max-w-3xl rounded-2xl border border-ink/10 bg-white p-6" x-data="{ email: '', result: null, loading: false, test() { this.loading = true; fetch('{{ route('admin.security.test-email') }}', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }, body: JSON.stringify({ email: this.email }) }).then(r => r.json()).then(d => { this.result = d; this.loading = false; }); } }">
        <h3 class="text-sm font-semibold text-ink">Test an address</h3>
        <div class="mt-3 flex items-center gap-2">
            <input type="email" x-model="email" placeholder="someone@example.com"
                   class="flex-1 rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink">
            <button type="button" @click="test()" class="rounded-xl border border-ink/15 bg-white px-4 py-2.5 text-sm font-semibold text-ink hover:border-saffron-deep">Check</button>
        </div>
        <template x-if="result">
            <p class="mt-3 rounded-xl px-4 py-2.5 text-sm font-medium"
               :class="result.result === 'blocked' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-800'">
                <span x-text="result.result === 'blocked' ? 'BLOCKED' : 'OK'"></span>
                <span class="font-mono text-xs" x-text="result.matched ? ' · matched '+result.matched : ''"></span>
            </p>
        </template>
    </div>
</x-admin-layout>
