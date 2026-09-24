<x-admin-layout title="Packs">
    @php
        /** @var \Illuminate\Pagination\LengthAwarePaginator $packs */
    @endphp

    <x-slot:actions>
        <a href="{{ route('admin.packs.create') }}" class="rounded-xl bg-saffron px-4 py-2 text-sm font-semibold text-ink transition hover:bg-saffron-deep">+ New pack</a>
    </x-slot:actions>

    <div class="overflow-hidden rounded-2xl border border-ink/10">
        <table class="min-w-full divide-y divide-ink/10 text-sm">
            <thead class="bg-paper-deep text-left text-xs uppercase tracking-wider text-ink0">
                <tr>
                    <th class="px-5 py-3 font-semibold">Pack</th>
                    <th class="hidden px-5 py-3 font-semibold sm:table-cell">Prompts</th>
                    <th class="hidden px-5 py-3 font-semibold sm:table-cell">Price</th>
                    <th class="px-5 py-3 font-semibold">Status</th>
                    <th class="px-5 py-3 text-right font-semibold">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-ink/10 bg-white">
                @forelse ($packs as $pack)
                    <tr class="transition hover:bg-paper-deep">
                        <td class="px-5 py-3.5">
                            <p class="font-medium text-ink">{{ $pack->name }}</p>
                            <p class="text-xs text-ink0">{{ Str::limit($pack->description, 60) }}</p>
                        </td>
                        <td class="hidden px-5 py-3.5 text-ink/60 sm:table-cell">{{ $pack->published_prompts_count }}</td>
                        <td class="hidden px-5 py-3.5 text-ink/60 sm:table-cell">{{ $pack->priceLabel() }}</td>
                        <td class="px-5 py-3.5">
                            <span class="rounded-md px-2 py-0.5 text-xs font-medium {{ $pack->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-paper-deep text-ink/60' }}">
                                {{ $pack->is_active ? 'active' : 'hidden' }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center justify-end gap-1.5">
                                <a href="{{ route('packs.show', $pack) }}" class="rounded-lg border border-ink/10 px-2.5 py-1 text-xs text-ink/80 transition hover:border-ink/25">View</a>
                                <a href="{{ route('admin.packs.edit', $pack) }}" class="rounded-lg border border-ink/10 px-2.5 py-1 text-xs text-ink/80 transition hover:border-saffron-deep hover:text-saffron-deep">Edit</a>
                                <form method="POST" action="{{ route('admin.packs.destroy', $pack) }}" onsubmit="return confirm('Delete this pack?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded-lg border border-rose-700/30 bg-rose-100 px-2.5 py-1 text-xs font-medium text-rose-700 transition hover:bg-rose-200">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-ink0">No packs yet — create your first bundle.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $packs->links() }}</div>
</x-admin-layout>
