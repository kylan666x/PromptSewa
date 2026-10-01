<x-admin-layout title="Frames">
    @php
        /** @var \Illuminate\Support\Collection<int, \App\Models\Frame> $frames */
    @endphp

    <h2 class="text-xl font-bold tracking-tight text-ink">Profile frames</h2>
    <p class="mt-1 text-xs text-ink/60">
        Cosmetic avatar rings (alpha PNG, 512px — JPEG rejected). Deleting a frame clears it from users automatically.
    </p>

    <form method="POST" action="{{ route('admin.frames.store') }}" enctype="multipart/form-data" class="mt-5 grid gap-4 rounded-2xl border border-ink/10 bg-white p-6 sm:grid-cols-3">
        @csrf
        <div>
            <label class="block text-xs font-medium text-ink/60">Name</label>
            <input type="text" name="name" required maxlength="80" class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink">
        </div>
        <div>
            <label class="block text-xs font-medium text-ink/60">Image (PNG/WebP, ≤2 MB, transparency required)</label>
            <input type="file" name="image" required accept="image/png,image/webp" class="mt-1.5 block w-full text-sm text-ink/70">
        </div>
        <div class="flex items-end gap-4">
            <label class="flex items-center gap-2 text-sm text-ink/80">
                <input type="checkbox" name="is_active" value="1" checked class="size-4 rounded border-ink/20 text-saffron-deep">
                Active
            </label>
            <button class="rounded-xl bg-saffron px-4 py-2.5 text-sm font-bold text-ink hover:bg-saffron-deep">Create frame</button>
        </div>
    </form>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @forelse ($frames as $frame)
            <div class="rounded-2xl border border-ink/10 bg-white p-5">
                <div class="flex items-center justify-between">
                    <p class="font-semibold text-ink">{{ $frame->name }}</p>
                    <span class="rounded-full px-2 py-0.5 font-mono text-[10px] font-bold {{ $frame->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-paper-deep text-ink/50' }}">
                        {{ $frame->is_active ? 'active' : 'inactive' }}
                    </span>
                </div>
                @if ($frame->image_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($frame->image_path) }}" alt="" class="mt-3 size-16">
                @endif
                <form method="POST" action="{{ route('admin.frames.destroy', $frame) }}" class="mt-3"
                      onsubmit="return confirm('Delete this frame? Users wearing it fall back to no frame.')">
                    @csrf
                    @method('DELETE')
                    <button class="rounded-lg border border-ink/10 px-2.5 py-1 text-xs font-semibold text-ink/60 hover:border-rose-500 hover:text-rose-700">Delete</button>
                </form>
            </div>
        @empty
            <p class="text-sm text-ink/50">No frames yet.</p>
        @endforelse
    </div>
</x-admin-layout>
