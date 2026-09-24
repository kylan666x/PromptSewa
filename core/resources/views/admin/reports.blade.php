<x-admin-layout title="Reports">
    @php
        /** @var \Illuminate\Pagination\LengthAwarePaginator $reports */
        /** @var array<string, int> $counts */
    @endphp

    <div class="flex flex-wrap gap-2">
        @foreach ([
            \App\Models\PromptReport::STATUS_OPEN => ['Open', $counts[\App\Models\PromptReport::STATUS_OPEN]],
            \App\Models\PromptReport::STATUS_RESOLVED => ['Resolved', $counts[\App\Models\PromptReport::STATUS_RESOLVED]],
            \App\Models\PromptReport::STATUS_DISMISSED => ['Dismissed', $counts[\App\Models\PromptReport::STATUS_DISMISSED]],
            '' => ['All', $counts['all']],
        ] as $statusKey => [$label, $count])
            <a href="{{ route('admin.reports.index', $statusKey === '' ? [] : ['status' => $statusKey]) }}"
               @class([
                   'rounded-xl border px-3.5 py-2 text-sm font-medium transition',
                   'border-saffron-deep bg-saffron/20 text-saffron-deep' => $currentStatus === $statusKey,
                   'border-ink/10 bg-paper-deep text-ink/80 hover:border-ink/25' => $currentStatus !== $statusKey,
               ])>
                {{ $label }} <span class="ml-1 text-xs text-ink0">{{ $count }}</span>
            </a>
        @endforeach
    </div>

    <div class="mt-6 space-y-4">
        @forelse ($reports as $report)
            <div class="rounded-2xl border border-ink/10 bg-white p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-md px-2 py-0.5 text-xs font-medium
                                {{ match ($report->status) {
                                    \App\Models\PromptReport::STATUS_OPEN => 'bg-saffron/25 text-saffron-deep',
                                    \App\Models\PromptReport::STATUS_RESOLVED => 'bg-emerald-100 text-emerald-800',
                                    \App\Models\PromptReport::STATUS_DISMISSED => 'bg-paper-deep text-ink/60',
                                    default => 'bg-paper-deep text-ink/80',
                                } }}">
                                {{ ucfirst($report->status) }}
                            </span>
                            <span class="font-mono text-xs text-ink0">{{ $report->reasonLabel() }}</span>
                        </div>
                        <p class="mt-2 font-medium text-ink">
                            <a href="{{ route('prompts.show', $report->prompt) }}" class="hover:text-saffron-deep">{{ $report->prompt?->title ?? 'Deleted prompt' }}</a>
                        </p>
                        <p class="mt-1 text-xs text-ink0">
                            Reported {{ $report->created_at->format('M j, Y g:i A') }}
                            @if ($report->reporter)
                                by {{ $report->reporter->name }}
                            @elseif ($report->reporter_email)
                                by {{ $report->reporter_email }}
                            @else
                                by a guest
                            @endif
                        </p>
                    </div>

                    <div class="flex items-center gap-1.5">
                        @if ($report->status !== \App\Models\PromptReport::STATUS_RESOLVED)
                            <form method="POST" action="{{ route('admin.reports.status', $report) }}">
                                @csrf
                                <input type="hidden" name="status" value="{{ \App\Models\PromptReport::STATUS_RESOLVED }}">
                                <button class="rounded-lg border border-emerald-700/40 bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-800 transition hover:bg-emerald-600/20">Resolve</button>
                            </form>
                        @endif
                        @if ($report->status !== \App\Models\PromptReport::STATUS_DISMISSED)
                            <form method="POST" action="{{ route('admin.reports.status', $report) }}">
                                @csrf
                                <input type="hidden" name="status" value="{{ \App\Models\PromptReport::STATUS_DISMISSED }}">
                                <button class="rounded-lg border border-ink/15 bg-paper-deep px-2.5 py-1 text-xs font-medium text-ink/80 transition hover:border-ink/40">Dismiss</button>
                            </form>
                        @endif
                        @if ($report->status === \App\Models\PromptReport::STATUS_OPEN)
                            <form method="POST" action="{{ route('admin.reports.status', $report) }}">
                                @csrf
                                <input type="hidden" name="status" value="{{ \App\Models\PromptReport::STATUS_OPEN }}">
                                <button class="rounded-lg border border-ink/15 px-2.5 py-1 text-xs font-medium text-ink/50 transition hover:border-ink/40" title="Reopen">Reopen</button>
                            </form>
                        @endif
                    </div>
                </div>

                <p class="mt-3 whitespace-pre-wrap rounded-xl bg-paper-deep px-4 py-3 text-sm leading-relaxed text-ink/80">{{ $report->message }}</p>

                @if ($report->resolver)
                    <p class="mt-2 text-xs text-ink0">Handled by {{ $report->resolver->name }} · {{ $report->resolved_at?->format('M j, Y') }}</p>
                @endif
            </div>
        @empty
            <div class="rounded-2xl border border-ink/10 bg-white p-10 text-center text-ink0">
                No reports here — the library is behaving. 🎉
            </div>
        @endforelse
    </div>

    <div class="mt-6">{{ $reports->links() }}</div>
</x-admin-layout>
