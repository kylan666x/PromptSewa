<x-app-layout>
    <x-seo :title="match ($tab) {
        'saved' => 'Saved prompts',
        'stats' => 'Stats',
        'feed' => 'Feed',
        'achievements' => 'Achievements',
        default => 'Your prompts',
    }" robots="noindex, follow"/>
    @php
        /** @var \App\Models\Prompt $prompt */
        /** @var array{total: int, published: int, pending: int, draft: int} $stats */
    @endphp

    {{-- ===== Admin entry card (staff only — full panel at /admin) ===== --}}
    @if (auth()->check() && auth()->user()->isModerator())
        <section class="mx-auto max-w-7xl px-4 pt-10 sm:px-6">
            <div class="rounded-3xl bg-ink p-6 shadow-card-hover sm:p-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-mono text-xs font-semibold uppercase tracking-[0.2em] text-saffron">Administration</p>
                        <h2 class="mt-1 text-2xl font-bold tracking-tight text-paper">Staff access</h2>
                        <p class="mt-1 text-sm text-paper/60">Moderation, orders, users, packs, payments and brand settings live in the admin panel.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('admin.dashboard') }}"
                           class="inline-flex items-center gap-1.5 rounded-full bg-saffron px-4 py-2.5 text-sm font-bold text-ink transition hover:bg-saffron-deep">
                            Open admin panel &rarr;
                        </a>
                        <a href="{{ route('admin.update') }}"
                           class="inline-flex items-center gap-1.5 rounded-full border border-paper/20 px-3 py-1.5 font-mono text-xs font-semibold text-paper/80 transition hover:border-saffron hover:text-saffron">
                            ⬆ Software update
                        </a>
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- ===== Personal workspace (every user) ===== --}}
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6">
        {{-- S4 (v1.9.0): the workspace header — title on the left, the tab
             rail and the create action on the right, settings-panel style.
             The rail keeps its G3 mobile scroll contract untouched. --}}
        <header class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-ink">{{ $tab === 'saved' ? 'Saved prompts' : 'Your prompts' }}</h1>
                <p class="mt-1 text-sm text-ink/60">{{ $tab === 'saved' ? "Listings you've bookmarked from the library." : "Everything you've created, including drafts and private listings." }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                {{-- T13 + G4/G5 (v1.7.0): dashboard tabs.
                     G3 (v1.7.4): below md the tab pills live in a horizontal
                     scroll rail so six tabs never wrap into three rows; the
                     active pill keeps its ink fill (WCAG contrast unchanged)
                     and the rail is keyboard-reachable via tabindex. The
                     "New prompt" CTA stays OUT of the rail — it is an
                     action, not a tab. --}}
                <div class="-mx-4 flex w-full snap-x snap-mandatory gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:w-auto sm:flex-wrap sm:overflow-visible sm:px-0 sm:pb-0"
                     role="navigation" aria-label="Dashboard sections" tabindex="0">
                    <a href="{{ route('dashboard') }}"
                       class="shrink-0 snap-start rounded-full px-4 py-2 text-sm font-semibold transition {{ $tab === 'prompts' ? 'bg-ink text-paper' : 'text-ink/70 hover:bg-paper-deep hover:text-ink' }}">Your prompts</a>
                    <a href="{{ route('dashboard', ['tab' => 'saved']) }}"
                       class="shrink-0 snap-start rounded-full px-4 py-2 text-sm font-semibold transition {{ $tab === 'saved' ? 'bg-ink text-paper' : 'text-ink/70 hover:bg-paper-deep hover:text-ink' }}">Saved</a>
                    <a href="{{ route('dashboard', ['tab' => 'stats']) }}"
                       class="shrink-0 snap-start rounded-full px-4 py-2 text-sm font-semibold transition {{ $tab === 'stats' ? 'bg-ink text-paper' : 'text-ink/70 hover:bg-paper-deep hover:text-ink' }}">Stats</a>
                    <a href="{{ route('dashboard', ['tab' => 'feed']) }}"
                       class="shrink-0 snap-start rounded-full px-4 py-2 text-sm font-semibold transition {{ $tab === 'feed' ? 'bg-ink text-paper' : 'text-ink/70 hover:bg-paper-deep hover:text-ink' }}">Feed</a>
                    {{-- P2b (v1.7.1): Achievements tab --}}
                    <a href="{{ route('dashboard', ['tab' => 'achievements']) }}"
                       class="shrink-0 snap-start rounded-full px-4 py-2 text-sm font-semibold transition {{ $tab === 'achievements' ? 'bg-ink text-paper' : 'text-ink/70 hover:bg-paper-deep hover:text-ink' }}">Achievements</a>
                    <a href="{{ route('dashboard.earnings') }}"
                       class="shrink-0 snap-start rounded-full px-4 py-2 text-sm font-semibold transition text-ink/70 hover:bg-paper-deep hover:text-ink">Earnings</a>
                </div>
                <a href="{{ route('dashboard.prompts.create') }}"
                   class="inline-flex items-center justify-center gap-2 rounded-full bg-saffron px-4 py-2.5 text-sm font-bold text-ink shadow-sm transition hover:bg-saffron-deep">
                    <span class="text-base leading-none">+</span> New prompt
                </a>
            </div>
        </header>

        @if ($tab === 'stats')
            {{-- ===== Stats tab (G5) ===== --}}
            {{-- G3: single column below md (full-width sparklines/cards),
                     two-up from lg — unchanged on desktop. --}}
            <div class="mt-8 grid grid-cols-2 gap-3 md:grid-cols-1 md:gap-6 lg:grid-cols-2">
                <section class="col-span-2 rounded-2xl border border-ink/10 bg-white p-4 shadow-sm md:p-6">
                    <h2 class="text-sm font-semibold text-ink">Views — last 30 days</h2>
                    <x-sparkline :series="$viewsSeries" label="Views over the last 30 days"/>
                    <p class="mt-1 font-mono text-xs text-ink/50">{{ number_format(array_sum($viewsSeries)) }} total · {{ number_format(max($viewsSeries)) }} best day</p>
                </section>
                <section class="col-span-2 rounded-2xl border border-ink/10 bg-white p-4 shadow-sm md:col-span-1 md:p-6">
                    <h2 class="text-sm font-semibold text-ink">Sales — last 30 days</h2>
                    <x-sparkline :series="$salesSeries" label="Paid orders over the last 30 days" stroke="#059669" fill="rgba(5, 150, 105, 0.12)"/>
                    <p class="mt-1 font-mono text-xs text-ink/50">{{ number_format(array_sum($salesSeries)) }} orders in the window</p>
                </section>
                <section class="col-span-2 rounded-2xl border border-ink/10 bg-white p-4 shadow-sm md:col-span-1 md:p-6">
                    <h2 class="text-sm font-semibold text-ink">Rating trend — last 30 days</h2>
                    <x-sparkline :series="$ratingSeries" label="Average rating over the last 30 days" stroke="#2563eb" fill="rgba(37, 99, 235, 0.10)"/>
                    <p class="mt-1 font-mono text-xs text-ink/50">{{ array_sum($ratingSeries) > 0 ? number_format(array_sum($ratingSeries) / count(array_filter($ratingSeries)), 1) : '0.0' }} avg across rated days</p>
                </section>
                <section class="col-span-2 rounded-2xl border border-ink/10 bg-white p-4 shadow-sm md:col-span-1 md:p-6">
                    <h2 class="text-sm font-semibold text-ink">Top prompts by views</h2>
                    @if ($topPrompts->isEmpty())
                        <p class="mt-3 text-sm text-ink/50">No published prompts yet — publish to start the clock.</p>
                    @else
                        <table class="mt-3 w-full text-sm">
                            <tbody class="divide-y divide-ink/10">
                                @foreach ($topPrompts as $top)
                                    <tr>
                                        <td class="py-2 font-medium text-ink"><a href="{{ route('prompts.show', $top) }}" class="hover:text-saffron-deep">{{ $top->title }}</a></td>
                                        {{-- F1 (v1.9.2): the per-listing sales number is the paid-lines count. --}}
                                        <td class="py-2 text-right font-mono text-xs text-ink/60">{{ number_format($top->views_count) }} views · {{ number_format($top->paid_sales_count) }} sales</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </section>
            </div>
        @elseif ($tab === 'feed')
            {{-- ===== Feed tab (G4, ink world) ===== --}}
            <div class="mt-8 space-y-3">
                @forelse ($feedEvents as $event)
                    <div class="rounded-2xl border border-white/10 bg-ink-soft p-4 text-paper">
                        <p class="flex flex-wrap items-center gap-2 text-sm">
                            <x-user-avatar :user="$event->actor" size="sm" :frame="$event->actor->activeFrame"/>
                            <span class="font-semibold">{{ $event->actor->name }}</span>
                            <span class="rounded-full bg-white/10 px-2 py-0.5 font-mono text-[10px] font-bold text-paper/80">Lv {{ \App\Services\GamificationService::levelForXp((int) $event->actor->xp) }}</span>
                            @if ($event->type === \App\Models\FeedEvent::TYPE_PROMPT_PUBLISHED)
                                <span class="text-paper/70">published</span> <span class="font-semibold">{{ $event->meta['title'] }}</span>
                            @elseif ($event->type === \App\Models\FeedEvent::TYPE_SALE_MILESTONE)
                                <span class="text-paper/70">hit</span> <span class="font-semibold">{{ number_format($event->meta['sales_count']) }} sales</span> <span class="text-paper/70">with {{ $event->meta['title'] }}</span>
                            @elseif ($event->type === \App\Models\FeedEvent::TYPE_BADGE_EARNED)
                                <span class="text-paper/70">earned</span> <span class="font-semibold">{{ $event->meta['badge_name'] }}</span>
                            @else
                                <span class="text-paper/70">launched pack</span> <span class="font-semibold">{{ $event->meta['name'] }}</span>
                            @endif
                        </p>
                        <p class="mt-1 font-mono text-[11px] text-paper/40">{{ $event->created_at->diffForHumans() }}</p>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-white/15 p-10 text-center text-sm text-paper/50">No activity yet.</div>
                @endforelse
                @if ($feedEvents->hasPages())
                    <div>{{ $feedEvents->links('pagination::tailwind') }}</div>
                @endif
            </div>
        @elseif ($tab === 'achievements')
            {{-- ===== Achievements tab (P2b, v1.7.1) ===== --}}
            <div class="mt-8 space-y-8">
                <section aria-label="Earned badges">
                    <h2 class="font-mono text-xs font-semibold uppercase tracking-widest text-ink/50">Earned badges</h2>
                    @if ($achievements['earned']->isEmpty())
                        <p class="mt-3 rounded-2xl border border-dashed border-ink/20 bg-white p-6 text-sm text-ink/60">No badges yet — publish your first prompt to earn one.</p>
                    @else
                        <div class="mt-3 flex flex-wrap gap-4">
                            @foreach ($achievements['earned'] as $earned)
                                <div class="flex items-center gap-2.5 rounded-2xl border border-ink/10 bg-white px-4 py-3 shadow-sm">
                                    @if ($earned->badge?->image_path)
                                        <img src="{{ Storage::disk('public')->url($earned->badge->image_path) }}" alt="" class="size-10">
                                    @else
                                        <span class="flex size-10 items-center justify-center rounded-full bg-saffron/25 text-base" aria-hidden="true">🏅</span>
                                    @endif
                                    <div>
                                        <p class="text-sm font-semibold text-ink">{{ $earned->badge?->name ?? 'Badge' }}</p>
                                        <p class="font-mono text-[10px] text-ink/50">awarded {{ $earned->awarded_at->format('M j, Y') }}</p>
                                        @if ($earned->reason)
                                            <p class="mt-0.5 max-w-[220px] truncate text-xs text-ink/50" title="{{ $earned->reason }}">{{ $earned->reason }}</p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>

                <section aria-label="Progress">
                    <h2 class="font-mono text-xs font-semibold uppercase tracking-widest text-ink/50">Progress</h2>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        @foreach ($achievements['progress'] as $row)
                            <div class="rounded-2xl border border-ink/10 bg-white p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-medium text-ink">{{ $row['label'] }}</p>
                                    @if ($row['earned'])
                                        <span class="shrink-0 rounded-full bg-emerald-100 px-2 py-0.5 font-mono text-[10px] font-bold text-emerald-800">earned</span>
                                    @endif
                                </div>
                                @if ($row['current'] !== null && $row['goal'] !== null && ! $row['earned'])
                                    @php $pct = $row['goal'] > 0 ? min(100, (int) floor($row['current'] / $row['goal'] * 100)) : 0; @endphp
                                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-paper-deep">
                                        <div class="h-full rounded-full bg-saffron" style="width: {{ $pct }}%"></div>
                                    </div>
                                    <p class="mt-1 font-mono text-[11px] text-ink/50">{{ $row['current'] }}/{{ $row['goal'] }}</p>
                                @elseif ($row['current'] === null && ! $row['earned'])
                                    <p class="mt-1 text-xs text-ink/50">Awarded by the community team.</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>
        @elseif ($tab === 'saved')
            {{-- ===== Saved tab (T13) ===== --}}
            <div class="mt-8">
                @if ($saved->isEmpty())
                    <div class="rounded-2xl border border-dashed border-ink/20 bg-white p-10 text-center">
                        <p class="text-sm text-ink/60">Nothing saved yet — tap the heart on any prompt to keep it here.</p>
                        <a href="{{ route('library.index') }}" class="mt-3 inline-block rounded-full bg-saffron px-4 py-2 text-sm font-bold text-ink transition hover:bg-saffron-deep">Browse the library</a>
                    </div>
                @else
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($saved as $bookmark)
                            @php $savedPrompt = $bookmark->prompt; @endphp
                            @continue($savedPrompt === null)
                            <div class="flex flex-col rounded-2xl border border-ink/10 bg-white p-5 shadow-sm">
                                <div class="flex items-start justify-between gap-2">
                                    <h3 class="font-semibold leading-snug text-ink">
                                        <a href="{{ route('prompts.show', $savedPrompt) }}" class="hover:text-saffron-deep">{{ $savedPrompt->title }}</a>
                                    </h3>
                                    <span class="shrink-0 rounded-md bg-paper-deep px-2 py-0.5 font-mono text-[10px] font-bold text-ink/60">{{ $savedPrompt->typeLabel() }}</span>
                                </div>
                                <p class="mt-2 line-clamp-2 text-xs leading-relaxed text-ink/50">{{ $savedPrompt->description }}</p>
                                <div class="mt-auto flex items-center justify-between pt-4">
                                    <span class="font-mono text-[11px] text-ink/50">{{ $savedPrompt->priceLabel() }}</span>
                                    <a href="{{ route('prompts.show', $savedPrompt) }}" class="rounded-full bg-saffron px-3 py-1.5 text-xs font-bold text-ink transition hover:bg-saffron-deep">Open prompt</a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @if ($saved->hasPages())
                        <div class="mt-6">{{ $saved->links('pagination::tailwind') }}</div>
                    @endif
                @endif
            </div>
        @else
        {{-- ===== Prompts tab (existing) ===== --}}

        {{-- S4 (v1.9.0): card-based stats — a tighter two-up/four-up grid
             so the numbers scan at a glance. --}}
        <div class="mt-8 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-2xl border border-ink/10 bg-white p-4 shadow-sm sm:p-5">
                <p class="font-mono text-xs font-semibold uppercase tracking-widest text-ink/50">Total prompts</p>
                <p class="mt-2 font-mono text-3xl font-bold tracking-tight text-ink">{{ $stats['total'] }}</p>
            </div>
            <div class="rounded-2xl border border-ink/10 bg-white p-4 shadow-sm sm:p-5">
                <p class="font-mono text-xs font-semibold uppercase tracking-widest text-ink/50">Published</p>
                <p class="mt-2 font-mono text-3xl font-bold tracking-tight text-emerald-700">{{ $stats['published'] }}</p>
            </div>
            <div class="rounded-2xl border border-ink/10 bg-white p-4 shadow-sm sm:p-5">
                <p class="font-mono text-xs font-semibold uppercase tracking-widest text-ink/50">In review</p>
                <p class="mt-2 font-mono text-3xl font-bold tracking-tight text-saffron-deep">{{ $stats['pending'] }}</p>
            </div>
            <div class="rounded-2xl border border-ink/10 bg-white p-4 shadow-sm sm:p-5">
                <p class="font-mono text-xs font-semibold uppercase tracking-widest text-ink/50">Drafts</p>
                <p class="mt-2 font-mono text-3xl font-bold tracking-tight text-ink/40">{{ $stats['draft'] }}</p>
            </div>
        </div>

        <div class="mt-10">
            @if ($prompts->isEmpty())
                <x-empty-state
                    title="No prompts yet"
                    description="Turn your best AI prompts into products — write one, price it, and submit it for review.">
                    <x-slot:actions>
                        <x-button href="{{ route('dashboard.prompts.create') }}" variant="primary">Create your first prompt</x-button>
                        <x-button href="{{ route('library.index') }}" variant="secondary">Browse the library</x-button>
                    </x-slot:actions>
                </x-empty-state>
            @else
                <div class="overflow-hidden rounded-2xl border border-ink/10 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-ink/10 text-sm">
                        <thead class="bg-paper-deep text-left font-mono text-xs uppercase tracking-wider text-ink/50">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Title</th>
                                <th class="px-5 py-3 font-semibold">Status</th>
                                <th class="hidden px-5 py-3 font-semibold sm:table-cell">Visibility</th>
                                <th class="hidden px-5 py-3 font-semibold sm:table-cell">Category</th>
                                <th class="hidden px-5 py-3 font-semibold md:table-cell">Versions</th>
                                <th class="hidden px-5 py-3 font-semibold md:table-cell">Price</th>
                                <th class="px-5 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink/10">
                            @foreach ($prompts as $prompt)
                                <tr class="transition hover:bg-paper-deep/50">
                                    <td class="px-5 py-3.5 font-medium text-ink">
                                        {{ $prompt->title }}
                                        @if ($prompt->status === \App\Models\Prompt::STATUS_PUBLISHED && $prompt->visibility === \App\Models\Prompt::VISIBILITY_PUBLIC)
                                            <a href="{{ route('prompts.show', $prompt) }}" class="ml-1 font-mono text-xs text-saffron-deep hover:text-ink">view ↗</a>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <span class="rounded-md px-2 py-0.5 font-mono text-xs font-medium
                                            {{ match ($prompt->status) {
                                                \App\Models\Prompt::STATUS_PUBLISHED => 'bg-emerald-100 text-emerald-800',
                                                \App\Models\Prompt::STATUS_PENDING => 'bg-saffron/25 text-ink',
                                                \App\Models\Prompt::STATUS_REJECTED => 'bg-rose-100 text-rose-700',
                                                default => 'bg-paper-deep text-ink/60',
                                            } }}">
                                            {{ $prompt->status }}
                                        </span>
                                    </td>
                                    <td class="hidden px-5 py-3.5 text-ink/60 sm:table-cell">{{ $prompt->visibility }}</td>
                                    <td class="hidden px-5 py-3.5 text-ink/60 sm:table-cell">{{ $prompt->category?->name ?? '—' }}</td>
                                    <td class="hidden px-5 py-3.5 font-mono text-ink/60 md:table-cell">{{ $prompt->versions_count }}</td>
                                    <td class="hidden px-5 py-3.5 font-mono text-ink/60 md:table-cell">{{ $prompt->priceLabel() }}</td>
                                    <td class="px-5 py-3.5 text-right">
                                        <a href="{{ route('dashboard.prompts.edit', $prompt) }}"
                                           class="inline-flex items-center gap-1 rounded-full border border-ink/15 px-2.5 py-1 font-mono text-xs text-ink/70 transition hover:border-saffron-deep hover:text-ink">
                                            Edit
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-6">
                    {{ $prompts->links() }}
                </div>
            @endif
        </div>
        @endif {{-- end prompts tab --}}
    </div>
</x-app-layout>
