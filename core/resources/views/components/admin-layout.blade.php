<x-app-layout>
    {{-- F1 (v1.5.1): admin pages get noindex SEO with the page title. --}}
    <x-seo :title="$title" robots="noindex, nofollow"/>
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="font-mono text-xs font-semibold uppercase tracking-[0.2em] text-saffron-deep">Administration</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-ink">{{ $title }}</h1>
            </div>
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>

        <nav class="mt-6 flex flex-wrap gap-2" aria-label="Admin sections">
            @php
                $adminNav = [
                    'admin.dashboard' => ['Overview', '▦'],
                    'admin.prompts.index' => ['Prompts', '✦'],
                    'admin.orders.index' => ['Orders', 'Rs'],
                    'admin.users.index' => ['Users', '👤'],
                    'admin.reports.index' => ['Reports', '⚑'],
                    'admin.packs.index' => ['Packs', '📦'],
                    'admin.payments.edit' => ['Payments', '💳'],
                    'admin.brand.edit' => ['Brand', '◆'],
                    'admin.tool-logos.index' => ['Tool logos', '✦'],
                    'admin.update' => ['Software update', '⬆'],
                    'admin.comp-grants.create' => ['Comp grants', '🎁'],
                    // P1b (v1.7.1): gamification doors — a feature without a nav
                    // entry is a missing feature (§6 watch-out). Controllers/routes
                    // existed since v1.7.0; these pills are the acceptance criterion.
                    'admin.badges.index' => ['Badges', '🏅'],
                    'admin.frames.index' => ['Frames', '◯'],
                    // T6 (v1.7.3): the trust gate ships with its nav entry in
                    // the same commit (§6.36 watch-out).
                    'admin.security.edit' => ['Security', '🛡'],
                ];
            @endphp
            @foreach ($adminNav as $route => [$label, $icon])
                <a href="{{ route($route) }}"
                   @class([
                       'rounded-full border px-3.5 py-2 text-sm font-medium transition',
                       'border-saffron-deep bg-saffron/25 font-semibold text-ink' => url()->current() === route($route),
                       'border-ink/15 bg-white text-ink/70 shadow-sm hover:border-ink/30 hover:text-ink' => url()->current() !== route($route),
                   ])>
                    <span class="mr-1.5 text-xs" aria-hidden="true">{{ $icon }}</span>{{ $label }}
                </a>
            @endforeach
        </nav>

        <div class="mt-8">
            {{ $slot }}
        </div>
    </div>
</x-app-layout>
