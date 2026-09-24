@php
    /** @var \App\Models\Prompt $prompt */
    /** @var bool $canEdit */
    /** @var bool $canViewFullBody */
    $latest = $prompt->latestVersion;
    $tags = collect($latest?->tags ?? []);
    $tools = collect($latest?->toolList() ?? []);
    $tips = collect($latest?->tipList() ?? []);
    $variableNames = $canViewFullBody ? ($latest?->variableNames() ?? []) : [];
    $isOwner = auth()->check() && auth()->user()->id === $prompt->user_id;
    $isPaid = $prompt->price_cents > 0;

    // Locked teaser: first lines of the body, never the whole thing.
    $teaserLines = collect(explode("\n", (string) $latest?->body))
        ->filter(fn (string $line) => trim($line) !== '');
    $teaser = $teaserLines->take(max(1, (int) floor($teaserLines->count() * 0.4)))->implode("\n");
    $reportEmail = (string) app(\App\Services\SettingsService::class)->get('support_email', 'admin@promptsewa.test');
@endphp

<x-app-layout>
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
        @if (session('report_submitted'))
            <div class="mb-6 flex items-start gap-3 rounded-2xl border border-emerald-700/30 bg-emerald-100 px-5 py-4" role="status">
                <span class="text-emerald-800">✓</span>
                <div>
                    <p class="text-sm font-semibold text-emerald-900">Report submitted — thank you.</p>
                    <p class="mt-0.5 text-xs text-emerald-800/80">A moderator will review it shortly. Action is taken from the Admin → Reports panel.</p>
                </div>
            </div>
        @endif

        {{-- Breadcrumb --}}
        <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-1.5 font-mono text-xs text-ink/50">
            <a href="{{ route('home') }}" class="transition hover:text-ink">Home</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('library.index') }}" class="transition hover:text-ink">Prompt library</a>
            @if ($prompt->category)
                <span aria-hidden="true">/</span>
                <a href="{{ route('library.category', $prompt->category) }}" class="transition hover:text-ink">{{ $prompt->category->name }}</a>
            @endif
            <span aria-hidden="true">/</span>
            <span class="text-ink/70">{{ Str::limit($prompt->title, 48) }}</span>
        </nav>

        <div class="mt-6 grid gap-10 lg:grid-cols-[1fr_340px]">
            {{-- Main column --}}
            <article>
                <x-prompt-cover :prompt="$prompt" large class="rounded-2xl border border-ink/10"/>

                <div class="mt-6 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-ink/15 bg-white px-2.5 py-1 font-mono text-[11px] font-medium text-ink/70">
                        {{ $prompt->typeLabel() }}
                    </span>
                    @if ($prompt->category)
                        <x-category-badge :category="$prompt->category"/>
                    @endif
                </div>

                <h1 class="mt-3 text-3xl font-bold tracking-tight text-ink sm:text-4xl">{{ $prompt->title }}</h1>

                <div class="mt-4 flex flex-wrap items-center gap-3 text-sm text-ink/60">
                    @if ($prompt->creator)
                        <a href="{{ route('creators.show', $prompt->creator) }}" class="group/creator flex items-center gap-2 rounded-full transition focus:outline-none focus-visible:ring-2 focus-visible:ring-saffron-deep" aria-label="View profile of {{ $prompt->creator->name }}">
                            <span class="flex size-8 items-center justify-center rounded-full bg-ink text-xs font-bold text-saffron transition group-hover/creator:bg-saffron group-hover/creator:text-ink">
                                {{ mb_substr($prompt->creator->name, 0, 1) }}
                            </span>
                            <span class="font-medium text-ink/80 transition group-hover/creator:text-ink group-hover/creator:underline decoration-saffron decoration-2 underline-offset-2">{{ $prompt->creator->name }}</span>
                        </a>
                    @else
                        <span class="flex items-center gap-2">
                            <span class="flex size-8 items-center justify-center rounded-full bg-ink text-xs font-bold text-saffron">?</span>
                            <span class="font-medium text-ink/80">Unknown creator</span>
                        </span>
                    @endif
                    <span class="text-ink/30" aria-hidden="true">·</span>
                    <span>Updated {{ $prompt->updated_at->format('M j, Y') }}</span>
                    <span class="text-ink/30" aria-hidden="true">·</span>
                    <span class="font-mono">{{ $prompt->versions->count() }} {{ Str::plural('version', $prompt->versions->count()) }}</span>
                    <span class="flex items-center gap-0.5 text-ink/50" title="Ratings arrive with reviews">
                        ★☆☆☆☆ <span class="ml-1 font-mono text-xs">No ratings yet</span>
                    </span>
                </div>

                <div class="prose prose-sm mt-6 max-w-3xl text-ink/80
                            prose-headings:text-ink prose-a:text-ink prose-a:decoration-saffron prose-a:decoration-2 prose-a:underline-offset-4 prose-strong:text-ink">
                    {!! Str::markdown($prompt->description, ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}
                </div>

                @if ($tools->isNotEmpty())
                    @php
                        $toolLogos = \App\Models\ToolLogo::query()->where('is_active', true)->pluck('logo_path', 'name');
                    @endphp
                    <section class="mt-8" aria-label="Recommended tools">
                        <h2 class="font-mono text-xs font-semibold uppercase tracking-widest text-ink/50">Works best in</h2>
                        <div class="mt-2.5 flex flex-wrap gap-2">
                            @foreach ($tools as $tool)
                                <span class="flex items-center gap-1.5 rounded-full border border-emerald-700/20 bg-emerald-100 px-3 py-1 font-mono text-xs font-medium text-emerald-900">
                                    @if ($toolLogos->has($tool) && $toolLogos->get($tool))
                                        <img src="{{ asset('storage/'.$toolLogos->get($tool)) }}" alt="" class="size-4 rounded object-contain">
                                    @endif
                                    {{ $tool }}
                                </span>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($latest?->audience)
                    <p class="mt-5 rounded-xl border border-ink/10 bg-white px-4 py-3 text-sm text-ink/80 shadow-sm">
                        <span class="font-semibold text-ink">Perfect for:</span> {{ $latest->audience }}
                    </p>
                @endif

                {{-- The prompt itself --}}
                <section class="mt-10" aria-label="The prompt">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="font-mono text-xs font-semibold uppercase tracking-wider text-ink/60">
                            The prompt @if ($latest)<span class="ml-1 rounded bg-saffron/30 px-1.5 py-0.5 text-saffron-deep">{{ $latest->label() }}</span>@endif
                        </h2>
                        @if ($canEdit)
                            <a href="{{ route('dashboard.prompts.edit', $prompt) }}" class="font-mono text-xs font-semibold text-ink underline decoration-saffron decoration-2 underline-offset-4 transition hover:text-saffron-deep">
                                Edit · View history ↗
                            </a>
                        @endif
                    </div>

                    @if ($canViewFullBody && $latest)
                        <div class="mt-3" x-data='promptViewer(@js($latest->body), @js($variableNames))'>
                            @if (count($variableNames) > 0)
                                <div class="rounded-2xl border border-ink/10 bg-white p-4 shadow-sm">
                                    <h3 class="font-mono text-xs font-semibold uppercase tracking-widest text-ink/50">Fill in the variables</h3>
                                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                        @foreach ($variableNames as $variable)
                                            <label class="block">
                                                <span class="block text-xs text-ink/70">{{ ucfirst(str_replace(['_', '-'], ' ', $variable)) }}</span>
                                                <input
                                                    type="text"
                                                    x-model="variables['{{ $variable }}']"
                                                    placeholder="{{ \App\Models\PromptVersion::placeholderFor($variable) }}"
                                                    class="mt-1 block w-full rounded-lg border border-ink/15 bg-white px-3 py-2 text-sm text-ink placeholder-ink/30 shadow-sm outline-none transition focus:border-saffron-deep focus:ring-2 focus:ring-saffron/40"
                                                >
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <div class="relative mt-3">
                                <button
                                    type="button"
                                    @click="copy()"
                                    class="absolute right-3 top-3 z-10 inline-flex items-center gap-1.5 rounded-full bg-saffron px-3 py-1.5 font-mono text-xs font-bold text-ink shadow-sm transition hover:bg-saffron-deep"
                                    :class="copied && 'bg-emerald-600 text-white'"
                                    aria-live="polite"
                                >
                                    <span x-text="copied ? 'Copied!' : 'Copy prompt'"></span>
                                </button>
                                <pre class="overflow-x-auto whitespace-pre-wrap rounded-2xl bg-ink p-5 pt-14 font-mono text-[13px] leading-relaxed text-paper/90 shadow-card" x-text="rendered"></pre>
                            </div>
                        </div>
                    @elseif ($latest)
                        {{-- Locked teaser for paid prompts --}}
                        <div class="relative mt-3 overflow-hidden rounded-2xl border border-ink/10">
                            <pre class="max-h-64 overflow-hidden whitespace-pre-wrap bg-ink p-5 font-mono text-[13px] leading-relaxed text-paper/70">{{ $teaser }}</pre>
                            <div class="absolute inset-0 bg-gradient-to-t from-ink via-ink/90 to-ink/40"></div>
                            <div class="absolute inset-0 flex flex-col items-center justify-center gap-2 px-6 text-center">
                                <svg class="size-6 text-saffron" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                                </svg>
                                <p class="text-sm font-semibold text-paper">The full prompt is locked</p>
                                <p class="max-w-xs text-xs leading-relaxed text-paper/60">
                                    Purchase includes the complete prompt, all future versions, and usage tips.
                                </p>
                                <span class="rounded-full bg-saffron px-3.5 py-1 font-mono text-xs font-bold text-ink">{{ $prompt->priceLabel() }}</span>
                            </div>
                        </div>
                    @endif
                </section>

                @if ($tips->isNotEmpty())
                    <section class="mt-8" aria-label="Usage tips">
                        <h2 class="font-mono text-xs font-semibold uppercase tracking-widest text-ink/50">How to get the best results</h2>
                        <ul class="mt-3 space-y-2">
                            @foreach ($tips as $tip)
                                <li class="flex gap-2.5 text-sm leading-relaxed text-ink/80">
                                    <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-saffron/30 font-mono text-[10px] font-bold text-ink">{{ $loop->iteration }}</span>
                                    {{ $tip }}
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($prompt->versions->count() > 0)
                    <section class="mt-10" aria-label="Version history">
                        <h2 class="font-mono text-xs font-semibold uppercase tracking-widest text-ink/50">Version history</h2>
                        <ol class="mt-3 space-y-2.5">
                            @foreach ($prompt->versions->sortByDesc('version_number')->take(6) as $version)
                                <li class="flex items-start gap-3 rounded-xl border border-ink/10 bg-white px-4 py-3 shadow-sm">
                                    <span class="mt-0.5 rounded-md bg-saffron/30 px-1.5 py-0.5 font-mono text-[11px] font-bold text-ink">{{ $version->label() }}</span>
                                    <div class="min-w-0 text-sm">
                                        <p class="text-ink/90">{{ $version->changelog ?? 'Updated prompt' }}</p>
                                        <p class="mt-0.5 font-mono text-xs text-ink/50">{{ $version->created_at->format('M j, Y') }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </section>
                @endif
            </article>

            {{-- Metadata sidebar --}}
            <aside class="space-y-5 lg:sticky lg:top-24 lg:self-start">
                <div class="rounded-3xl bg-ink p-5 shadow-card-hover">
                    <p class="font-mono text-xs font-semibold uppercase tracking-widest text-paper/50">License</p>
                    <p class="mt-2 flex items-baseline gap-2">
                        <span class="font-mono text-3xl font-bold tracking-tight {{ $isPaid ? 'text-saffron' : 'text-emerald-300' }}">{{ $prompt->priceLabel() }}</span>
                        @if ($isPaid)<span class="text-sm text-paper/40">one-time</span>@endif
                    </p>
                    <p class="mt-2 text-xs leading-relaxed text-paper/60">
                        @if ($prompt->license_tier === \App\Models\Prompt::LICENSE_COMMERCIAL)
                            Commercial license — use in client work and products you sell.
                        @else
                            Personal license — use in your own projects.
                        @endif
                        Includes every future version of this prompt.
                    </p>

                    <div class="mt-4">
                        @if (! $isPaid)
                            <span class="flex w-full items-center justify-center gap-2 rounded-full bg-emerald-400/20 px-4 py-2.5 text-sm font-semibold text-emerald-300">
                                Free — copy it above
                            </span>
                        @elseif ($canViewFullBody)
                            <span class="flex w-full items-center justify-center gap-2 rounded-full border border-emerald-400/50 bg-emerald-400/10 px-4 py-2.5 text-sm font-semibold text-emerald-300">
                                ✓ You own this prompt
                            </span>
                        @else
                            @auth
                                <form method="POST" action="{{ route('checkout.prompts.buy', $prompt) }}">
                                    @csrf
                                    <button
                                        type="submit"
                                        class="w-full rounded-full bg-saffron px-4 py-2.5 text-sm font-bold text-ink transition hover:bg-saffron-deep"
                                    >
                                        Buy now — {{ $prompt->priceLabel() }}
                                    </button>
                                </form>
                            @else
                                <a href="{{ route('login') }}"
                                   class="flex w-full items-center justify-center gap-2 rounded-full bg-saffron px-4 py-2.5 text-sm font-bold text-ink transition hover:bg-saffron-deep"
                                >
                                    Log in to buy — {{ $prompt->priceLabel() }}
                                </a>
                            @endauth
                        @endif
                    </div>
                </div>

                <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-sm">
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink/50">Category</dt>
                            <dd class="text-right text-ink/90">{{ $prompt->category?->name ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink/50">Type</dt>
                            <dd class="text-right text-ink/90">{{ $prompt->typeLabel() }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink/50">Created</dt>
                            <dd class="text-right font-mono text-ink/90">{{ $prompt->created_at->format('M j, Y') }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink/50">Updated</dt>
                            <dd class="text-right font-mono text-ink/90">{{ $prompt->updated_at->format('M j, Y') }}</dd>
                        </div>
                    </dl>

                    @if ($tags->isNotEmpty())
                        <div class="mt-4 border-t border-ink/10 pt-4">
                            <p class="font-mono text-xs font-semibold uppercase tracking-widest text-ink/50">Tags</p>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach ($tags as $tag)
                                    <span class="rounded-md border border-ink/10 bg-paper-deep px-2 py-0.5 font-mono text-[11px] text-ink/60">{{ $tag }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                @if ($isOwner)
                    <div class="rounded-2xl border border-saffron-deep/40 bg-saffron/10 p-5">
                        <p class="font-mono text-xs font-semibold uppercase tracking-widest text-saffron-deep">Creator controls</p>
                        <div class="mt-3 flex flex-col gap-2">
                            <a href="{{ route('dashboard.prompts.edit', $prompt) }}" class="flex items-center justify-center rounded-full bg-saffron px-4 py-2 text-sm font-bold text-ink transition hover:bg-saffron-deep">
                                Edit prompt
                            </a>
                            <a href="{{ route('dashboard') }}" class="flex items-center justify-center rounded-full border border-ink/15 bg-white px-4 py-2 text-sm font-medium text-ink transition hover:border-ink/40">
                                My dashboard
                            </a>
                        </div>
                    </div>
                @endif

                <a href="{{ route('prompts.report.create', $prompt) }}"
                   class="block text-center font-mono text-xs text-ink/40 transition hover:text-rose-700">
                    ⚑ Report this prompt
                </a>
            </aside>
        </div>
    </div>
</x-app-layout>
