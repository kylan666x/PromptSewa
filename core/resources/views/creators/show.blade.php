<x-app-layout>
    <x-seo
        :title="$creator->name"
        :description="$creator->bio ?? ('AI prompts by '.$creator->name.' on PromptSewa.')"
        :jsonLd="[
            '@context' => 'https://schema.org',
            '@type' => 'ProfilePage',
            'mainEntity' => [
                '@type' => 'Person',
                'name' => $creator->name,
                'description' => $creator->bio,
                'url' => url()->current(),
            ],
        ]"
    />
    @php
        /** @var \App\Models\User $creator */
        /** @var \Illuminate\Pagination\LengthAwarePaginator $prompts */
        $isOwner = auth()->check() && auth()->id() === $creator->id;
        // Deterministic gradient banner when no custom upload exists.
        $bannerHues = [
            'from-saffron via-amber-400 to-orange-500',
            'from-ink via-stone-700 to-saffron-deep',
            'from-orange-300 via-saffron to-amber-500',
            'from-stone-500 via-ink-soft to-ink',
            'from-yellow-300 via-saffron to-rose-400',
            'from-emerald-400 via-teal-500 to-saffron',
        ];
        $bannerClass = $creator->banner_path
            ? null
            : $bannerHues[$creator->id % count($bannerHues)];
    @endphp

    {{-- Cover banner — edge-to-edge inside the content column, X/Facebook-style --}}
    <div class="mx-auto max-w-5xl px-4 pt-6 sm:px-6">
        <div class="relative h-40 overflow-hidden rounded-3xl shadow-card sm:h-56">
            @if ($creator->banner_path)
                <img src="{{ Storage::url($creator->banner_path) }}" alt="Banner of {{ $creator->name }}"
                     class="absolute inset-0 size-full object-cover">
            @elseif ($bannerClass)
                <div class="absolute inset-0 bg-gradient-to-br {{ $bannerClass }}">
                    <div class="absolute inset-0 opacity-30 [background-image:radial-gradient(circle_at_2px_2px,rgba(255,255,255,0.35)_2px,transparent_0)] [background-size:28px_28px]"></div>
                </div>
            @endif
        </div>
    </div>

    <div class="mx-auto max-w-5xl px-4 sm:px-6">
        {{-- Identity row (UI-003): ONLY the avatar overlaps the banner
             (X/Facebook-style). The text block lives in its own flex-column
             container below the banner's bottom edge — no negative margins,
             guaranteed pt clearance — so name/handle/badges never collide
             with the photo, on any screen size or name length. --}}
        <div class="relative -mt-12 px-1 sm:-mt-16">
            <div class="flex flex-col gap-3 pt-3 sm:flex-row sm:items-end sm:justify-between sm:pt-4">
                <div class="flex min-w-0 items-end gap-4">
                    <x-user-avatar :user="$creator" size="md"
                                   class="relative size-24 rounded-3xl border-4 border-paper bg-saffron text-4xl shadow-card-hover sm:size-28"/>

                    {{-- Text block: normal document flow, pt clearance, truncate --}}
                    <div class="min-w-0 flex-1 pb-1">
                        <div class="flex min-w-0 items-center gap-2">
                            <h1 class="min-w-0 truncate text-2xl font-bold tracking-tight text-ink sm:text-3xl">{{ $creator->name }}</h1>
                            <span class="shrink-0"><x-verified-badge :user="$creator" size="lg"/></span>
                        </div>
                        @if ($creator->username)
                            <x-user-handle :user="$creator" class="mt-0.5 block"/>
                        @endif
                        <p class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-sm text-ink/50">
                            @if ($creator->isAtLeast(\App\Models\User::ROLE_CREATOR))
                                <span class="rounded-full bg-saffron/20 px-2.5 py-0.5 font-mono text-[11px] font-bold text-saffron-deep">Creator</span>
                            @endif
                            @if ($creator->isModerator())
                                <span class="rounded-full border border-ink/15 px-2.5 py-0.5 font-mono text-[11px] font-medium text-ink/70">Staff</span>
                            @endif
                            <span class="font-mono text-xs">Joined {{ $stats['joined']->format('M Y') }}</span>
                        </p>
                    </div>
                </div>

                @if ($isOwner)
                    <a href="{{ route('dashboard') }}"
                       class="shrink-0 self-start rounded-full border-2 border-ink/80 bg-white px-5 py-2 text-sm font-bold text-ink shadow-sm transition hover:-translate-y-0.5 hover:bg-ink hover:text-paper hover:shadow-card sm:self-auto">
                        Edit profile
                    </a>
                @endif
            </div>
        </div>

        {{-- Bio + stats strip --}}
        <div class="mt-4 px-1">
            @if ($creator->bio)
                <p class="max-w-2xl text-[15px] leading-relaxed text-ink/80">{{ $creator->bio }}</p>
            @endif

            <dl class="mt-4 flex flex-wrap gap-x-8 gap-y-2">
                <div class="flex items-baseline gap-1.5">
                    <dd class="text-lg font-bold text-ink">{{ number_format($stats['prompts']) }}</dd>
                    <dt class="text-sm text-ink/50">{{ \Illuminate\Support\Str::plural('prompt', $stats['prompts']) }}</dt>
                </div>
                <div class="flex items-baseline gap-1.5">
                    <dd class="text-lg font-bold text-ink">{{ number_format($stats['total_sales']) }}</dd>
                    <dt class="text-sm text-ink/50">{{ \Illuminate\Support\Str::plural('sale', $stats['total_sales']) }}</dt>
                </div>
            </dl>
        </div>

        {{-- Catalog --}}
        <section class="mt-8 pb-16" aria-label="Prompts by {{ $creator->name }}">
            <h2 class="border-b border-ink/10 pb-3 font-mono text-xs font-semibold uppercase tracking-[0.2em] text-ink/60">Prompts</h2>

            @if ($prompts->isEmpty())
                <div class="mt-6">
                    <x-empty-state
                        title="Nothing published yet"
                        description="{{ $creator->name }} hasn't published any prompts yet. Check back soon."
                    />
                </div>
            @else
                <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($prompts as $prompt)
                        <x-prompt-card :prompt="$prompt"/>
                    @endforeach
                </div>

                <div class="mt-10">
                    {{ $prompts->links() }}
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
