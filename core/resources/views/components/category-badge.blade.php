@props(['category'])

<a href="{{ route('library.category', $category) }}"
   class="inline-flex items-center gap-1 rounded-md border border-ink/10 bg-paper-deep px-2 py-0.5 font-mono text-[11px] font-medium text-ink/70 transition hover:border-saffron-deep hover:text-ink">
    {{ $category->name }}
</a>
