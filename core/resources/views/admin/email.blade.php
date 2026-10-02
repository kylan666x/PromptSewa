<x-admin-layout title="Email">
    @php
        /** @var string $mailer */
        /** @var array<int, string> $mailers */
        /** @var array<int, string> $encryptions */
        /** @var string $host */
        /** @var string $port */
        /** @var string $encryption */
        /** @var string $username */
        /** @var bool $passwordSaved */
        /** @var string $fromAddress */
        /** @var string $fromName */
        /** @var string $activeMailer */
        /** @var string $activeHost */
        /** @var int $activePort */
        /** @var string $brandFallback */
        /** @var string $siteName */
    @endphp

    <h2 class="text-xl font-bold tracking-tight text-ink">Email</h2>
    <p class="mt-1 max-w-3xl text-xs leading-relaxed text-ink/60">
        Password resets — and every future receipt — travel on this rail. It defaults to
        <span class="font-mono">sendmail</span> (the host's own Exim), which needs no setup on cPanel.
        Switch to SMTP only if the host blocks it. Credentials are encrypted at rest and this page
        never shows them again.
    </p>

    <div class="mt-4 flex flex-wrap items-center gap-2 rounded-xl border border-ink/10 bg-white px-4 py-3 text-xs text-ink/60">
        <span class="font-semibold uppercase tracking-wide text-ink/50">Live now</span>
        <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 font-mono text-[11px] font-bold text-emerald-800">{{ $activeMailer }}</span>
        @if ($activeMailer === 'smtp')
            <span class="font-mono text-[11px]">{{ $activeHost }}:{{ $activePort }}</span>
        @else
            <span class="text-ink/50">no host — the local binary</span>
        @endif
    </div>

    <form method="POST" action="{{ route('admin.email.update') }}" class="mt-6 max-w-3xl space-y-6">
        @csrf
        @method('PUT')

        <div class="rounded-2xl border border-ink/10 bg-white p-6">
            <h3 class="text-sm font-semibold text-ink">Transport</h3>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-ink/60" for="mail_mailer">Mailer</label>
                    <select id="mail_mailer" name="mail_mailer" class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink">
                        @foreach ($mailers as $option)
                            <option value="{{ $option }}" @selected($mailer === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1.5 text-[11px] leading-relaxed text-ink/50">
                        <span class="font-mono">sendmail</span> = cPanel Exim (try this first) ·
                        <span class="font-mono">smtp</span> = a real mail server ·
                        <span class="font-mono">log</span> = write to the log, send nothing.
                    </p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-ink/60" for="mail_encryption">Encryption</label>
                    <select id="mail_encryption" name="mail_encryption" class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink">
                        @foreach ($encryptions as $option)
                            <option value="{{ $option }}" @selected($encryption === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1.5 text-[11px] leading-relaxed text-ink/50">
                        <span class="font-mono">ssl</span> on port 465 is the common mailbox default.
                    </p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-ink/60" for="mail_host">SMTP host</label>
                    <input id="mail_host" name="mail_host" type="text" value="{{ $host }}" placeholder="mail.babal.host"
                           autocomplete="off" spellcheck="false"
                           class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink">
                </div>

                <div>
                    <label class="block text-xs font-medium text-ink/60" for="mail_port">Port</label>
                    <input id="mail_port" name="mail_port" type="number" value="{{ $port }}" placeholder="465" min="1" max="65535"
                           class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink">
                </div>

                <div>
                    <label class="block text-xs font-medium text-ink/60" for="mail_username">Username</label>
                    <input id="mail_username" name="mail_username" type="text" value="{{ $username }}"
                           placeholder="no-reply@yourdomain.com"
                           autocomplete="off" spellcheck="false"
                           class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink">
                </div>

                <div>
                    <label class="block text-xs font-medium text-ink/60" for="mail_password">
                        Password
                        @if ($passwordSaved)
                            <span class="ml-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800">✓ saved (encrypted)</span>
                        @endif
                    </label>
                    {{-- A2: write-only secret carries the view toggle like every
                         other password field. It is rendered empty, always. --}}
                    <x-password-input id="mail_password" name="mail_password" :label="null"
                                      autocomplete="new-password"
                                      placeholder="{{ $passwordSaved ? 'Leave empty to keep the saved password' : 'Mailbox password' }}"/>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-ink/10 bg-white p-6">
            <h3 class="text-sm font-semibold text-ink">From address</h3>
            <p class="mt-1 text-xs leading-relaxed text-ink/60">
                Left blank this falls back to Brand &rarr; contact email
                @if ($brandFallback !== '')
                    (<span class="font-mono">{{ $brandFallback }}</span>)
                @else
                    (Brand has no contact email yet)
                @endif,
                then to the <span class="font-mono">MAIL_FROM_*</span> env values. A mismatched From is
                the usual reason resets land in spam.
            </p>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-ink/60" for="mail_from_address">From address</label>
                    <input id="mail_from_address" name="mail_from_address" type="email" value="{{ $fromAddress }}"
                           placeholder="no-reply@yourdomain.com"
                           class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink">
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink/60" for="mail_from_name">From name</label>
                    <input id="mail_from_name" name="mail_from_name" type="text" value="{{ $fromName }}" placeholder="{{ $siteName }}"
                           class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink">
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button class="rounded-xl bg-saffron px-5 py-2.5 text-sm font-semibold text-ink transition hover:bg-saffron-deep">Save email settings</button>
            <span class="text-xs text-ink/50">Saving takes effect on the next request — no config cache to clear.</span>
        </div>
    </form>

    {{-- A3: the probe. Its result is a flash on the next render, so a failed
         send leaves the operator with a class + one line to act on. --}}
    <div class="mt-8 max-w-3xl rounded-2xl border border-ink/10 bg-white p-6">
        <h3 class="text-sm font-semibold text-ink">Send test email</h3>
        <p class="mt-1 text-xs leading-relaxed text-ink/60">
            Sends a real message on the CURRENTLY configured rail to your own address
            (<span class="font-mono">{{ auth()->user()->email }}</span>). Try this right after saving.
        </p>

        <form method="POST" action="{{ route('admin.email.test') }}" class="mt-4">
            @csrf
            <button class="rounded-xl border-2 border-ink/80 bg-white px-5 py-2.5 text-sm font-bold uppercase tracking-wide text-ink shadow-[0_4px_0_0_rgba(23,23,21,0.8)] transition hover:-translate-y-0.5 hover:shadow-[0_6px_0_0_rgba(23,23,21,0.8)] active:translate-y-0.5 active:shadow-none">
                Send test email
            </button>
        </form>
    </div>
</x-admin-layout>