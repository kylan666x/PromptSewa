<x-admin-layout title="{{ $pack->exists ? 'Edit pack' : 'New pack' }}">
    @php
        /** @var \App\Models\Pack $pack */
        /** @var \Illuminate\Support\Collection<int, \App\Models\Prompt> $prompts */
        /** @var \Illuminate\Support\Collection $selectedIds */
        $priceNpr = old('price_npr', $pack->exists ? $pack->priceNpr() : 0);
        $selected = old('prompt_ids', $selectedIds->all());
    @endphp

    <x-slot:actions>
        <a href="{{ route('admin.packs.index') }}" class="rounded-xl border border-ink/10 px-4 py-2 text-sm text-ink/80 transition hover:border-ink/25">← All packs</a>
    </x-slot:actions>

    <form method="POST" action="{{ $pack->exists ? route('admin.packs.update', $pack) : route('admin.packs.store') }}" class="max-w-3xl space-y-6">
        @csrf
        @if ($pack->exists) @method('PUT') @endif

        <div class="rounded-2xl border border-ink/10 bg-white p-6 space-y-5">
            <div>
                <label class="block text-xs font-medium text-ink/60">Pack name <span class="text-saffron-deep">*</span></label>
                <input type="text" name="name" required maxlength="160" value="{{ old('name', $pack->name) }}"
                       placeholder="e.g. The Complete ChatGPT Content Kit"
                       class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink outline-none focus:border-saffron-deep">
                @error('name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-ink/60">Description</label>
                <textarea name="description" rows="3" maxlength="2000"
                          class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink outline-none focus:border-saffron-deep"
                          placeholder="What's inside and who it's for.">{{ old('description', $pack->description) }}</textarea>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="block text-xs font-medium text-ink/60">Price (NPR) <span class="text-saffron-deep">*</span></label>
                    <input type="number" name="price_npr" required min="0" value="{{ $priceNpr }}"
                           class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink outline-none focus:border-saffron-deep">
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink/60">Sort position</label>
                    <input type="number" name="position" min="0" value="{{ old('position', $pack->position ?? 0) }}"
                           class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink outline-none focus:border-saffron-deep">
                </div>
                <div class="flex items-end pb-1">
                    <label class="flex items-center gap-2">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $pack->is_active))
                               class="size-4 rounded border-ink/20 bg-paper-deep text-saffron-deep focus:ring-saffron/40">
                        <span class="text-sm text-ink/80">Visible in the store</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-ink/10 bg-white p-6">
            <h3 class="text-sm font-semibold text-ink">Prompts in this pack</h3>
            <p class="mt-1 text-xs text-ink0">Only published prompts are listed. Buyers get a license to every prompt here (current members included).</p>

            <div class="mt-4 max-h-96 space-y-1.5 overflow-y-auto rounded-xl border border-ink/10 bg-paper-deep p-3">
                @forelse ($prompts as $prompt)
                    <label class="flex cursor-pointer items-center justify-between gap-3 rounded-lg px-2.5 py-1.5 transition hover:bg-paper-deep">
                        <span class="flex min-w-0 items-center gap-2.5">
                            <input type="checkbox" name="prompt_ids[]" value="{{ $prompt->id }}" @checked(in_array($prompt->id, $selected))
                                   class="size-4 rounded border-ink/20 bg-paper-deep text-saffron-deep focus:ring-saffron/40">
                            <span class="truncate text-sm text-ink/90">{{ $prompt->title }}</span>
                        </span>
                        <span class="shrink-0 text-xs text-ink0">{{ $prompt->priceLabel() }}</span>
                    </label>
                @empty
                    <p class="px-2 py-4 text-sm text-ink0">No published prompts yet — publish some first.</p>
                @endforelse
            </div>
            @error('prompt_ids.*') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>

        <button class="rounded-xl bg-saffron px-5 py-2.5 text-sm font-semibold text-ink transition hover:bg-saffron-deep">
            {{ $pack->exists ? 'Save changes' : 'Create pack' }}
        </button>
    </form>
</x-admin-layout>
