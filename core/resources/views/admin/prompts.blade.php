<x-admin-layout title="Prompts">
    @php
        /** @var \App\Models\Prompt \Illuminate\Pagination\LengthAwarePaginator $prompts */
        /** @var array<string, int> $counts */
    @endphp

    <div class="flex flex-wrap gap-2">
        @foreach ([
            '' => ['All', $counts['all']],
            \App\Models\Prompt::STATUS_PENDING => ['Pending', $counts[\App\Models\Prompt::STATUS_PENDING]],
            \App\Models\Prompt::STATUS_PUBLISHED => ['Published', $counts[\App\Models\Prompt::STATUS_PUBLISHED]],
            \App\Models\Prompt::STATUS_REJECTED => ['Rejected', $counts[\App\Models\Prompt::STATUS_REJECTED]],
            \App\Models\Prompt::STATUS_DRAFT => ['Drafts', $counts[\App\Models\Prompt::STATUS_DRAFT]],
        ] as $statusKey => [$label, $count])
            <a href="{{ route('admin.prompts.index', $statusKey === '' ? [] : ['status' => $statusKey]) }}"
               @class([
                   'rounded-xl border px-3.5 py-2 text-sm font-medium transition',
                   'border-saffron-deep bg-saffron/20 text-saffron-deep' => $currentStatus === $statusKey,
                   'border-ink/10 bg-paper-deep text-ink/80 hover:border-ink/25' => $currentStatus !== $statusKey,
               ])>
                {{ $label }} <span class="ml-1 text-xs text-ink0">{{ $count }}</span>
            </a>
        @endforeach
    </div>

    <div class="mt-6 overflow-hidden rounded-2xl border border-ink/10">
        <table class="min-w-full divide-y divide-ink/10 text-sm">
            <thead class="bg-paper-deep text-left text-xs uppercase tracking-wider text-ink0">
                <tr>
                    <th class="px-5 py-3 font-semibold">Title</th>
                    <th class="px-5 py-3 font-semibold">Creator</th>
                    <th class="hidden px-5 py-3 font-semibold sm:table-cell">Category</th>
                    <th class="hidden px-5 py-3 font-semibold md:table-cell">Price</th>
                    <th class="px-5 py-3 font-semibold">Status</th>
                    <th class="px-5 py-3 text-right font-semibold">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-ink/10 bg-white">
                @forelse ($prompts as $prompt)
                    <tr class="transition hover:bg-paper-deep">
                        <td class="px-5 py-3.5 font-medium text-ink">
                            <a href="{{ route('prompts.show', $prompt) }}" class="hover:text-saffron-deep">{{ $prompt->title }}</a>
                        </td>
                        <td class="px-5 py-3.5 text-ink/60">{{ $prompt->creator?->name ?? '—' }}</td>
                        <td class="hidden px-5 py-3.5 text-ink/60 sm:table-cell">{{ $prompt->category?->name ?? '—' }}</td>
                        <td class="hidden px-5 py-3.5 text-ink/60 md:table-cell">{{ $prompt->priceLabel() }}</td>
                        <td class="px-5 py-3.5">
                            <span class="rounded-md px-2 py-0.5 text-xs font-medium
                                {{ match ($prompt->status) {
                                    \App\Models\Prompt::STATUS_PUBLISHED => 'bg-emerald-100 text-emerald-800',
                                    \App\Models\Prompt::STATUS_PENDING => 'bg-saffron/25 text-saffron-deep',
                                    \App\Models\Prompt::STATUS_REJECTED => 'bg-rose-100 text-rose-700',
                                    default => 'bg-paper-deep text-ink/80',
                                } }}">
                                {{ $prompt->status }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center justify-end gap-1.5">
                                @if ($prompt->status !== \App\Models\Prompt::STATUS_PUBLISHED)
                                    <form method="POST" action="{{ route('admin.prompts.status', $prompt) }}">
                                        @csrf
                                        <input type="hidden" name="status" value="{{ \App\Models\Prompt::STATUS_PUBLISHED }}">
                                        <button class="rounded-lg border border-emerald-700/40 bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-800 transition hover:bg-emerald-600/20">Publish</button>
                                    </form>
                                @endif
                                @if ($prompt->status !== \App\Models\Prompt::STATUS_REJECTED)
                                    <form method="POST" action="{{ route('admin.prompts.status', $prompt) }}">
                                        @csrf
                                        <input type="hidden" name="status" value="{{ \App\Models\Prompt::STATUS_REJECTED }}">
                                        <button class="rounded-lg border border-rose-700/30 bg-rose-100 px-2.5 py-1 text-xs font-medium text-rose-700 transition hover:bg-rose-200">Reject</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-ink0">No prompts in this state.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $prompts->links() }}</div>
</x-admin-layout>
