<x-admin-layout title="Tool logos">
    @php
        /** @var \Illuminate\Support\Collection<int, \App\Models\ToolLogo> $tools */
    @endphp

    <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
        <div class="rounded-2xl border border-ink/10 bg-white p-6">
            <h3 class="text-sm font-semibold text-ink">Registered AI tools</h3>
            <p class="mt-1 text-xs text-ink0">These logos appear on prompt cards and detail pages. Match the tool name exactly as creators type it (e.g. "ChatGPT", "Midjourney").</p>

            <div class="mt-5 space-y-3">
                @forelse ($tools as $tool)
                    <div class="flex items-center justify-between gap-3 rounded-xl border border-ink/10 bg-paper-deep px-4 py-3">
                        <div class="flex min-w-0 items-center gap-3">
                            @if ($tool->logo_path)
                                <img src="{{ asset('storage/'.$tool->logo_path) }}" alt="{{ $tool->name }}" class="size-9 rounded-lg border border-ink/10 bg-white object-contain p-1">
                            @else
                                <span class="flex size-9 items-center justify-center rounded-lg border border-ink/10 bg-paper-deep text-xs font-bold text-ink/60">{{ mb_substr($tool->name, 0, 2) }}</span>
                            @endif
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-ink">{{ $tool->name }}</p>
                                <p class="text-xs {{ $tool->is_active ? 'text-emerald-700' : 'text-ink0' }}">{{ $tool->is_active ? 'active' : 'hidden' }}</p>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-1.5">
                            <form method="POST" action="{{ route('admin.tool-logos.update', $tool) }}" enctype="multipart/form-data" class="flex items-center gap-1.5">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="name" value="{{ $tool->name }}">
                                <input type="hidden" name="is_active" value="{{ $tool->is_active ? '0' : '1' }}">
                                <label class="cursor-pointer rounded-lg border border-ink/10 px-2.5 py-1 text-xs text-ink/80 transition hover:border-saffron-deep hover:text-saffron-deep">
                                    {{ $tool->logo_path ? 'Replace logo' : 'Upload logo' }}
                                    <input type="file" name="logo" accept="image/*" class="hidden" onchange="this.form.submit()">
                                </label>
                                <button class="rounded-lg border border-ink/10 px-2.5 py-1 text-xs text-ink/80 transition hover:border-ink/25">{{ $tool->is_active ? 'Hide' : 'Show' }}</button>
                            </form>
                            <form method="POST" action="{{ route('admin.tool-logos.destroy', $tool) }}" onsubmit="return confirm('Remove this tool?')">
                                @csrf
                                @method('DELETE')
                                <button class="rounded-lg border border-rose-700/30 bg-rose-100 px-2.5 py-1 text-xs font-medium text-rose-700 transition hover:bg-rose-200">✕</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="rounded-xl border border-ink/10 bg-paper-deep px-4 py-8 text-center text-sm text-ink0">No tools yet — add ChatGPT, Gemini, Midjourney on the right.</p>
                @endforelse
            </div>
        </div>

        <form method="POST" action="{{ route('admin.tool-logos.store') }}" enctype="multipart/form-data" class="h-fit rounded-2xl border border-ink/10 bg-white p-6">
            @csrf
            <h3 class="text-sm font-semibold text-ink">Add a tool</h3>
            <div class="mt-4 space-y-4">
                <div>
                    <label class="block text-xs font-medium text-ink/60">Tool name <span class="text-saffron-deep">*</span></label>
                    <input type="text" name="name" required maxlength="60" placeholder="e.g. ChatGPT"
                           class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink outline-none focus:border-saffron-deep">
                    @error('name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink/60">Logo (PNG/SVG, square works best)</label>
                    <input type="file" name="logo" accept="image/*"
                           class="mt-1.5 block w-full text-sm text-ink/60 file:mr-3 file:rounded-lg file:border-0 file:bg-paper-deep file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-ink/90">
                    @error('logo') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <button class="w-full rounded-xl bg-saffron px-4 py-2.5 text-sm font-semibold text-ink transition hover:bg-saffron-deep">Add tool</button>
            </div>
        </form>
    </div>
</x-admin-layout>
