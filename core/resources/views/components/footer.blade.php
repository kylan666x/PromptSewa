@props([
    'siteName' => 'PromptSewa',
    'contactEmail' => '',
    'supportEmail' => '',
    'brandLogoPath' => '',
])

<footer class="border-t border-ink/10 bg-paper-deep/60">
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6">
        <div class="flex flex-col justify-between gap-8 md:flex-row md:items-start">
            <div class="max-w-sm">
                <a href="{{ route('home') }}" class="flex items-center gap-2 text-lg font-semibold tracking-tight text-ink">
                    @if ($brandLogoPath !== '')
                        <img src="{{ asset('storage/'.$brandLogoPath) }}" alt="{{ $siteName }} logo" class="size-7 rounded-lg object-contain">
                    @else
                        <span class="flex size-7 items-center justify-center rounded-lg bg-saffron text-xs font-bold text-ink">{{ mb_substr($siteName, 0, 2) }}</span>
                    @endif
                    {{ $siteName }}
                </a>
                <p class="mt-3 text-sm leading-relaxed text-ink/60">
                    Version control for your AI prompts — discover, test, buy and sell
                    battle-tested prompts with licensing built in.
                </p>
            </div>

            <div class="flex gap-14">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-ink/50">Marketplace</p>
                    <ul class="mt-3 space-y-2 text-sm">
                        <li><a href="{{ route('library.index') }}" class="text-ink/70 transition hover:text-ink">Prompt library</a></li>
                        <li><a href="{{ route('packs.index') }}" class="text-ink/70 transition hover:text-ink">Packs</a></li>
                        <li><a href="{{ route('pages.about') }}" class="text-ink/70 transition hover:text-ink">About</a></li>
                    </ul>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-ink/50">Contact</p>
                    <ul class="mt-3 space-y-2 text-sm">
                        @if ($contactEmail !== '')
                            <li><a href="mailto:{{ $contactEmail }}" class="text-ink/70 transition hover:text-ink">{{ $contactEmail }}</a></li>
                        @endif
                        @if ($supportEmail !== '')
                            <li><a href="mailto:{{ $supportEmail }}" class="text-ink/70 transition hover:text-ink">{{ $supportEmail }}</a></li>
                        @endif
                        @if ($contactEmail === '' && $supportEmail === '')
                            <li><span class="text-ink/40">Set contact emails in admin → Brand</span></li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>

        <div class="mt-10 flex flex-col gap-2 border-t border-ink/10 pt-6 text-xs text-ink/50 sm:flex-row sm:items-center sm:justify-between">
            <p>© {{ date('Y') }} {{ $siteName }}. All rights reserved.</p>
            <p class="font-mono">Prices in NPR · one-time purchases</p>
        </div>
    </div>
</footer>
