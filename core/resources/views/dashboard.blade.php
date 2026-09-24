<x-app-layout>
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
                            Open admin panel →
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
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-ink">Your prompts</h1>
                <p class="mt-1 text-sm text-ink/60">Everything you've created, including drafts and private listings.</p>
            </div>
            <a href="{{ route('dashboard.prompts.create') }}"
               class="inline-flex items-center justify-center gap-2 rounded-full bg-saffron px-4 py-2.5 text-sm font-bold text-ink shadow-sm transition hover:bg-saffron-deep">
                <span class="text-base leading-none">+</span> New prompt
            </a>
        </header>

        {{-- Stat cards --}}
        <div class="mt-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-sm">
                <p class="font-mono text-xs font-semibold uppercase tracking-widest text-ink/50">Total prompts</p>
                <p class="mt-2 font-mono text-3xl font-bold tracking-tight text-ink">{{ $stats['total'] }}</p>
            </div>
            <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-sm">
                <p class="font-mono text-xs font-semibold uppercase tracking-widest text-ink/50">Published</p>
                <p class="mt-2 font-mono text-3xl font-bold tracking-tight text-emerald-700">{{ $stats['published'] }}</p>
            </div>
            <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-sm">
                <p class="font-mono text-xs font-semibold uppercase tracking-widest text-ink/50">In review</p>
                <p class="mt-2 font-mono text-3xl font-bold tracking-tight text-saffron-deep">{{ $stats['pending'] }}</p>
            </div>
            <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-sm">
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
    </div>
</x-app-layout>
