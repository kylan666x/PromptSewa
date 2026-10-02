@php
    /** @var \App\Models\Prompt $prompt */
    /** @var \Illuminate\Support\Collection<int, array{label: string, changelog: string, author: ?string, author_missing: bool, created_at: \Illuminate\Support\Carbon}> $versions */
    /** @var bool $isPaid */
@endphp

<x-app-layout>
    <x-seo :title="$prompt->title.' — version history'" :description="$prompt->description" :canonical="route('prompts.show', $prompt)"/>
    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        {{-- Breadcrumb: Home / Library / {category} / {prompt} / History --}}
        <nav aria-label="Breadcrumb" class="font-mono text-xs text-ink/50">
            <ol class="flex flex-wrap items-center gap-1.5">
                <li><a href="{{ route('home') }}" class="transition hover:text-saffron-deep">Home</a></li>
                <li aria-hidden="true">&rarr;</li>
                <li><a href="{{ route('library.index') }}" class="transition hover:text-saffron-deep">Library</a></li>
                @if ($prompt->category)
                    <li aria-hidden="true">&rarr;</li>
                    <li><a href="{{ route('library.index', ['category' => $prompt->category->slug]) }}" class="transition hover:text-saffron-deep">{{ $prompt->category->name }}</a></li>
                @endif
                <li aria-hidden="true">&rarr;</li>
                <li class="max-w-[16rem] truncate"><a href="{{ route('prompts.show', $prompt) }}" class="transition hover:text-saffron-deep">{{ $prompt->title }}</a></li>
                <li aria-hidden="true">&rarr;</li>
                <li class="font-semibold text-ink" aria-current="page">History</li>
            </ol>
        </nav>

        <header class="mt-6">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-saffron-deep">Version history</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-ink">{{ $prompt->title }}</h1>
            <p class="mt-2 flex flex-wrap items-center gap-2 text-sm text-ink/60">
                <span>{{ $versions->count() }} {{ \Illuminate\Support\Str::plural('version', $versions->count()) }}</span>
                <span aria-hidden="true">&middot;</span>
                <a href="{{ route('creators.show', $prompt->creator) }}" class="flex items-center gap-1.5 transition hover:text-saffron-deep">
                    <x-user-avatar :user="$prompt->creator" size="xs" :frame="$prompt->creator->activeFrame"/>
                    <x-user-handle :user="$prompt->creator" size="text-sm"/>
                </a>
                @if ($isPaid)
                    <span aria-hidden="true">&middot;</span>
                    <span class="rounded-full bg-paper-deep px-2 py-0.5 font-mono text-[11px] text-ink/60">Metadata only — bodies stay behind the paywall</span>
                @endif
            </p>
        </header>

        <ol class="mt-8 space-y-3">
            @forelse ($versions as $version)
                <li class="rounded-2xl border border-ink/10 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="flex items-center gap-2">
                            <span class="rounded-full bg-saffron/25 px-2.5 py-0.5 font-mono text-xs font-bold text-saffron-deep">{{ $version['label'] }}</span>
                            <span class="text-sm font-semibold text-ink">{{ $version['changelog'] !== '' ? $version['changelog'] : 'No changelog note' }}</span>
                        </p>
                        <p class="font-mono text-xs text-ink/50">{{ $version['created_at']->format('M j, Y') }} ·
                            {{-- H2 (v1.5.2): missing/deleted author → "Former creator" chip,
                                 never a crash or a blank row. --}}
                            @if ($version['author_missing'])
                                <span class="rounded-full bg-paper-deep px-2 py-0.5 font-mono text-[10px] text-ink/50">Former creator</span>
                            @else
                                {{ $version['author'] }}
                            @endif
                        </p>
                    </div>

                    {{-- T7 (v1.5.0): snapshot honesty + full-snapshot rendering --}}
                    @if (! $version['has_snapshot'])
                        <p class="mt-3 rounded-lg bg-paper-deep px-3 py-2 font-mono text-[11px] text-ink/50">Snapshot not captured before v1.5.0 — metadata only</p>
                    @elseif ($version['body'] !== null)
                        <pre class="mt-3 max-h-64 overflow-auto whitespace-pre-wrap rounded-lg bg-paper-deep p-3 font-mono text-xs text-ink/80">{{ $version['body'] }}</pre>
                        @if ($version['variables'] !== [])
                            <p class="mt-2 flex flex-wrap gap-1">
                                @foreach ($version['variables'] as $variable)
                                    <span class="rounded-full border border-ink/10 px-2 py-0.5 font-mono text-[10px] text-ink/60">&#123;&#123; {{ $variable }} &#125;&#125;</span>
                                @endforeach
                            </p>
                        @endif
                        @if ($version['tools'] !== [])
                            <p class="mt-1.5 font-mono text-[10px] text-ink/50">Tools: {{ implode(', ', $version['tools']) }}</p>
                        @endif
                        @if ($version['can_restore'])
                            <form method="POST" action="{{ route('prompts.versions.restore', [$prompt, $version['label']]) }}" class="mt-3">
                                @csrf
                                <button class="rounded-lg border border-ink/10 px-3 py-1.5 text-xs font-semibold text-ink/70 transition hover:border-saffron-deep hover:text-saffron-deep">Restore this version</button>
                            </form>
                        @endif
                    @endif
                </li>
            @empty
                <li class="rounded-2xl border border-ink/10 bg-white p-8 text-center text-sm text-ink/60">No versions recorded yet.</li>
            @endforelse
        </ol>

        <a href="{{ route('prompts.show', $prompt) }}"
           class="mt-8 inline-block font-mono text-xs font-semibold text-ink underline decoration-saffron decoration-2 underline-offset-4 transition hover:text-saffron-deep">
            &larr; Back to the prompt
        </a>
    </div>
</x-app-layout>
