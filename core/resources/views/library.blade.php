<x-app-layout>
    @php
        $heading = $category ? $category->name : ($q !== '' ? 'Search results' : 'Prompt Library');
        $subheading = $category
            ? 'Every published prompt in '.$category->name.'.'
            : ($q !== '' ? 'Prompts matching “'.$q.'”.' : 'Hand-reviewed prompts, ready to copy and run.');
    @endphp

    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6">
        <header class="flex flex-col gap-1">
            <h1 class="text-3xl font-bold tracking-tight text-ink">{{ $heading }}</h1>
            <p class="text-sm text-ink/60">{{ $subheading }}</p>
        </header>

        <div class="mt-8 flex flex-col gap-8 lg:flex-row">
            {{-- Category sidebar --}}
            <aside class="shrink-0 lg:w-56">
                <nav aria-label="Categories" class="rounded-2xl border border-ink/10 bg-white p-3 shadow-sm">
                    <p class="px-2 pb-2 font-mono text-xs font-semibold uppercase tracking-wider text-ink/50">Categories</p>
                    <ul class="flex flex-row gap-1 overflow-x-auto lg:flex-col lg:overflow-visible">
                        <li>
                            <a href="{{ route('library.index') }}"
                               class="flex items-center justify-between gap-2 whitespace-nowrap rounded-xl px-3 py-2 text-sm transition {{ $category === null ? 'bg-saffron/25 font-semibold text-ink' : 'text-ink/70 hover:bg-paper-deep hover:text-ink' }}">
                                All prompts
                            </a>
                        </li>
                        @foreach ($categories as $sidebarCategory)
                            <li>
                                <a href="{{ route('library.category', $sidebarCategory) }}"
                                   class="flex items-center justify-between gap-2 whitespace-nowrap rounded-xl px-3 py-2 text-sm transition {{ ($category?->is($sidebarCategory)) ? 'bg-saffron/25 font-semibold text-ink' : 'text-ink/70 hover:bg-paper-deep hover:text-ink' }}">
                                    <span>{{ $sidebarCategory->name }}</span>
                                    <span class="font-mono text-xs text-ink/40">{{ $sidebarCategory->prompts_count }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            </aside>

            {{-- Prompt grid --}}
            <div class="min-w-0 flex-1">
                @if ($prompts->isEmpty())
                    <x-empty-state
                        title="No prompts found"
                        description="{{ $q !== '' ? 'Nothing matched your search. Try a different keyword or browse by category.' : 'No prompts have been published in this category yet.' }}">
                        <x-slot:actions>
                            <x-button href="{{ route('library.index') }}" variant="secondary">Clear search</x-button>
                        </x-slot:actions>
                    </x-empty-state>
                @else
                    <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($prompts as $prompt)
                            <x-prompt-card :prompt="$prompt"/>
                        @endforeach
                    </div>

                    <div class="mt-10">
                        {{ $prompts->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
