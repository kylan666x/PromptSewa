<x-app-layout>
    <div class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
        <h1 class="text-4xl font-bold tracking-tight text-ink">Quality prompts, <em class="italic">fairly priced.</em></h1>

        <div class="prose prose-sm mt-8 max-w-none text-ink/80
                    prose-headings:text-ink prose-a:text-ink prose-a:decoration-saffron prose-a:decoration-2 prose-a:underline-offset-4 prose-strong:text-ink">
            {!! Str::markdown($aboutBody !== '' ? $aboutBody : 'Content coming soon.', ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}
        </div>

        <div class="mt-10 flex flex-wrap gap-3">
            <a href="{{ route('library.index') }}" class="rounded-full bg-saffron px-5 py-2.5 text-sm font-bold text-ink shadow-sm transition hover:bg-saffron-deep">Browse the library</a>
            <a href="{{ route('packs.index') }}" class="rounded-full border border-ink/15 bg-white px-5 py-2.5 text-sm font-semibold text-ink shadow-sm transition hover:border-ink/40">See prompt packs</a>
        </div>
    </div>
</x-app-layout>
