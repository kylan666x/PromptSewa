<x-app-layout>
    @php
        /** @var \Illuminate\Support\Collection<int, array{level: string, line: string}>|null $log */
        /** @var string|null $tokenHint */
        $log = $log ?? null;
        $tokenHint = $tokenHint ?? null;
        $failed = $failed ?? false;
    @endphp

    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <header>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-saffron-deep">Administration</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-ink">Update PromptSewa</h1>
            <p class="mt-1 text-sm text-ink/60">Upload a release zip (<code class="text-ink/80">promptsewa-upload.zip</code>) — it will be extracted, migrated and brought back online in one step. The site briefly enters maintenance mode.</p>
        </header>

        @if ($log !== null)
            <div class="mt-8 rounded-2xl border border-ink/10 bg-white p-6">
                <h2 class="text-sm font-semibold text-ink">Update log</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach ($log as $entry)
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5 font-semibold
                                {{ match ($entry['level']) {
                                    'ok' => 'text-emerald-700',
                                    'bad' => 'text-rose-600',
                                    'warn' => 'text-saffron-deep',
                                    default => 'text-sky-800',
                                } }}">
                                {{ match ($entry['level']) { 'ok' => '✓', 'bad' => '✘', 'warn' => '⚠', default => 'ℹ' } }}
                            </span>
                            <span class="text-ink/80">{{ $entry['line'] }}</span>
                        </li>
                    @endforeach
                </ul>
                @if ($failed)
                    <p class="mt-4 rounded-xl border border-rose-500/20 bg-rose-100 px-4 py-3 text-sm font-medium text-rose-700">Update failed — check <code>core/storage/logs/laravel.log</code> and the direct <code>update.php</code> page for details.</p>
                @else
                    <p class="mt-4 rounded-xl border border-emerald-500/20 bg-emerald-100 px-4 py-3 text-sm font-medium text-emerald-800">Update complete — the site is live with the new release.</p>
                @endif
            </div>
        @endif

        @if ($tokenHint === 'missing')
            <div class="mt-8 rounded-2xl border border-saffron-deep/60 bg-saffron/20 p-5 text-sm text-saffron-deep">
                <b>Update token missing.</b> The docroot needs a <code>.update-token</code> file (hidden file) containing a long random string. Create it via cPanel → File Manager with <i>Show Hidden Files</i> enabled, then reload this page. Example content: <code>{{ bin2hex(random_bytes(16)) }}</code>
            </div>
        @endif

        <form method="post" action="{{ route('dashboard.update.run') }}" enctype="multipart/form-data" class="mt-8 rounded-2xl border border-ink/10 bg-white p-6">
            @csrf
            <label for="release_zip" class="block text-sm font-semibold text-ink/90">Release zip</label>
            <input id="release_zip" type="file" name="release_zip" accept=".zip" required
                   class="mt-2 block w-full cursor-pointer rounded-xl border border-ink/10 bg-white px-4 py-3 text-sm text-ink/80 file:mr-4 file:cursor-pointer file:rounded-lg file:border-0 file:bg-saffron file:px-4 file:py-2 file:text-sm file:font-semibold file:text-ink hover:file:bg-saffron-deep"/>
            @error('release_zip')
                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
            @enderror

            <p class="mt-4 text-xs leading-relaxed text-ink0">
                The zip must be a PromptSewa release (containing <code>core/</code> and <code>public_html/</code>).
                Your existing <code>.env</code>, database and update token are preserved.
                @if (isset($zipLimit) && $zipLimit < 104857600)
                    This host allows uploads up to <strong>{{ number_format($zipLimit / 1048576) }} MB</strong> — larger releases must go via cPanel extraction + the direct
                    <a href="{{ url('update.php') }}" class="text-saffron-deep hover:text-saffron-deep">update.php page</a>.
                @else
                    Upload issues? Use the direct <a href="{{ url('update.php') }}" class="text-saffron-deep hover:text-saffron-deep">update.php page</a> or cPanel extraction instead.
                @endif
            </p>

            <button type="submit"
                    class="mt-6 inline-flex items-center gap-2 rounded-xl bg-saffron px-5 py-2.5 text-sm font-semibold text-ink shadow-lg shadow-saffron/30 transition hover:bg-saffron-deep"
                    onclick="this.disabled=true; this.textContent='Updating…'; this.form.submit();">
                ⬆ Upload &amp; update
            </button>
        </form>
    </div>
</x-app-layout>
