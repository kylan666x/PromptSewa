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
                // R2 (v1.7.7 raid): the third tuple slot marks an admin-only
                // door. A pill that 403s for the moderator seeing it is a dead
                // action — the pills are hidden for non-admins, and the role
                // matrix + dead-link scan lock both directions.
                $isAdmin = auth()->user()?->isAdmin() ?? false;
                $adminNav = [
                    'admin.dashboard' => ['Overview', '▦', false],
                    'admin.prompts.index' => ['Prompts', '✦', false],
                    'admin.orders.index' => ['Orders', 'Rs', false],
                    'admin.users.index' => ['Users', '👤', false],
                    'admin.reports.index' => ['Reports', '⚑', false],
                    'admin.packs.index' => ['Packs', '📦', false],
                    'admin.payments.edit' => ['Payments', '💳', false],
                    'admin.brand.edit' => ['Brand', '◆', false],
                    'admin.tool-logos.index' => ['Tool logos', '✦', false],
                    'admin.update' => ['Software update', '⬆', false],
                    'admin.comp-grants.create' => ['Comp grants', '🎁', true],
                    // P1b (v1.7.1): gamification doors — a feature without a nav
                    // entry is a missing feature (§6 watch-out). Controllers/routes
                    // existed since v1.7.0; these pills are the acceptance criterion.
                    'admin.badges.index' => ['Badges', '🏅', true],
                    'admin.frames.index' => ['Frames', '◯', true],
                    // T6 (v1.7.3): the trust gate ships with its nav entry in
                    // the same commit (§6.36 watch-out).
                    'admin.security.edit' => ['Security', '🛡', true],
                    // A3 (v1.7.6): the mail rail. Without this pill the mail
                    // settings exist but nobody can reach them — which is how
                    // "mail is broken on prod" stays a mystery for a month.
                    'admin.email.edit' => ['Email', '✉', true],
                ];
            @endphp
            @foreach ($adminNav as $route => [$label, $icon, $adminOnly])
                @continue($adminOnly && ! $isAdmin)
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
