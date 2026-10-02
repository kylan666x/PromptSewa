<x-admin-layout title="Frames">
    @php
        /** @var \Illuminate\Support\Collection<int, \App\Models\Frame> $frames */
        /** @var \Illuminate\Support\Collection<int, \App\Models\User> $users */
        /** @var \Illuminate\Support\Collection<int, \App\Models\UserFrameUnlock> $unlocks */
        /** @var list<string> $criteria */
        /** @var list<string> $animations */
    @endphp

    <h2 class="text-xl font-bold tracking-tight text-ink">Profile frames</h2>
    <p class="mt-1 text-xs text-ink/60">
        Cosmetic avatar rings (alpha PNG/WebP, ≤512px — animated GIF/WebP kept byte-identical, JPEG rejected).
        Criterion empty = free for everyone. Deleting a frame clears it from users automatically.
    </p>

    <form method="POST" action="{{ route('admin.frames.store') }}" enctype="multipart/form-data" class="mt-5 grid gap-4 rounded-2xl border border-ink/10 bg-white p-6 sm:grid-cols-3">
        @csrf
        <div>
            <label class="block text-xs font-medium text-ink/60">Name</label>
            <input type="text" name="name" required maxlength="80" class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink">
        </div>
        <div>
            <label class="block text-xs font-medium text-ink/60">Image (PNG/WebP, ≤2 MB, transparency required)</label>
            <input type="file" name="image" required accept="image/png,image/webp,image/gif" class="mt-1.5 block w-full text-sm text-ink/70">
        </div>
        <div>
            <label class="block text-xs font-medium text-ink/60">Criterion (empty = free for all)</label>
            <select name="criterion" class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink">
                <option value="">Free — anyone can equip</option>
                @foreach ($criteria as $criterion)
                    <option value="{{ $criterion }}">{{ str_replace('_', ' ', $criterion) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-ink/60">Animation (decorative motion)</label>
            <select name="animation" class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink">
                @foreach ($animations as $animation)
                    <option value="{{ $animation }}">{{ ucfirst($animation) }}</option>
                @endforeach
            </select>
        </div>
        {{-- v1.7.5 (R3): the photo is inset to the art's own transparent hole,
             so the hole has to be a per-frame number. The bounds are rendered
             from Frame::HOLE_MIN/MAX/DEFAULT — the SAME constants the model
             enforces and the tests assert (§6 hotfix-43 lesson: never hand-copy
             a bound into markup). --}}
        <div>
            <label for="hole_percent" class="block text-xs font-medium text-ink/60">Centre hole %</label>
            <input
                type="number"
                id="hole_percent"
                name="hole_percent"
                value="{{ \App\Models\Frame::HOLE_DEFAULT }}"
                min="{{ \App\Models\Frame::HOLE_MIN }}"
                max="{{ \App\Models\Frame::HOLE_MAX }}"
                step="1"
                class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink"
            >
            <p class="mt-1 text-[11px] leading-snug text-ink/50">
                Match your PNG's transparent hole — {{ \App\Models\Frame::HOLE_DEFAULT }} for the shipped art.
                The photo is inset to fill it exactly; a wrong value shows the ring sitting off-centre.
            </p>
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
                {{-- W2: thumbnail sits on the PAPER card — no dark backing box,
                     so a flattened (black-corner) upload is visible instantly. --}}
                <div class="mt-3 flex items-center justify-center rounded-xl bg-paper-deep p-3">
                    @if ($frame->image_path)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($frame->image_path) }}" alt="" class="size-16">
                    @endif
                </div>
                <p class="mt-2 font-mono text-[10px] text-ink/50">
                    {{ $frame->criterion ? 'Locked · '.str_replace('_', ' ', $frame->criterion) : 'Free' }}
                    @if (($frame->animation ?? 'none') !== 'none') · anim: {{ $frame->animation }} @endif
                    · hole {{ $frame->hole_percent ?? \App\Models\Frame::HOLE_DEFAULT }}%
                </p>

                {{-- W4: per-frame criterion edit + v1.7.5 hole edit. The hole
                     number is rendered with the SAME min/max constants the
                     model enforces, and the current value is posted as-is so
                     saving the criterion never silently resets the geometry. --}}
                <form method="POST" action="{{ route('admin.frames.update', $frame) }}" class="mt-2 flex items-center gap-1.5">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="name" value="{{ $frame->name }}">
                    <input type="hidden" name="animation" value="{{ $frame->animation ?? 'none' }}">
                    <input
                        type="number"
                        name="hole_percent"
                        value="{{ $frame->hole_percent ?? \App\Models\Frame::HOLE_DEFAULT }}"
                        min="{{ \App\Models\Frame::HOLE_MIN }}"
                        max="{{ \App\Models\Frame::HOLE_MAX }}"
                        step="1"
                        aria-label="Centre hole percent for {{ $frame->name }}"
                        class="w-16 rounded-lg border border-ink/10 bg-paper-deep px-2 py-1 text-xs text-ink"
                    >
                    <select name="criterion" class="flex-1 rounded-lg border border-ink/10 bg-paper-deep px-2 py-1 text-xs text-ink">
                        <option value="" @selected($frame->criterion === null)>Free</option>
                        @foreach ($criteria as $criterion)
                            <option value="{{ $criterion }}" @selected($frame->criterion === $criterion)>{{ str_replace('_', ' ', $criterion) }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-lg border border-ink/10 px-2 py-1 text-xs text-ink/80 hover:border-saffron-deep hover:text-saffron-deep">Set</button>
                </form>

                <form method="POST" action="{{ route('admin.frames.destroy', $frame) }}" class="mt-2"
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

    {{-- W4: manual award — audited (granted_by + mandatory reason), idempotent --}}
    <div class="mt-10 rounded-2xl border border-ink/10 bg-white p-6">
        <h3 class="text-sm font-semibold text-ink">Manual award</h3>
        <p class="mt-1 text-xs text-ink/60">Grant a frame to a user. Awarded rows are audited (your id + the reason) and idempotent — granting twice changes nothing.</p>

        <form method="POST" action="{{ route('admin.frames.award') }}" class="mt-4 grid gap-4 sm:grid-cols-4">
            @csrf
            <div>
                <label class="block text-xs font-medium text-ink/60">User</label>
                <select name="user_id" required class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink">
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} {{ $user->username ? '@'.$user->username : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-ink/60">Frame</label>
                <select name="frame_id" required class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink">
                    @foreach ($frames as $frame)
                        <option value="{{ $frame->id }}">{{ $frame->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-ink/60">Reason (required — audited)</label>
                <input type="text" name="reason" required minlength="3" maxlength="255" placeholder="e.g. Founder drop — Radiant series #4"
                       class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink">
            </div>
            <div class="sm:col-span-4">
                <button class="rounded-xl bg-saffron px-4 py-2.5 text-sm font-bold text-ink hover:bg-saffron-deep">Grant frame</button>
            </div>
        </form>

        @if ($unlocks->isNotEmpty())
            <h4 class="mt-6 font-mono text-xs font-semibold uppercase tracking-widest text-ink/50">Recent grants</h4>
            <ul class="mt-3 space-y-2">
                @foreach ($unlocks as $unlock)
                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-ink/10 bg-paper-deep/60 px-4 py-2.5 text-sm">
                        <span class="min-w-0">
                            <strong class="text-ink">{{ $unlock->user?->name ?? 'deleted user' }}</strong>
                            <span class="text-ink/60">— {{ $unlock->frame?->name ?? 'deleted frame' }}</span>
                            <span class="ml-1 font-mono text-[10px] text-ink/40">{{ $unlock->source }} · {{ $unlock->grantor?->name ?? 'system' }}</span>
                        </span>
                        <span class="flex items-center gap-2">
                            <span class="max-w-[16rem] truncate font-mono text-[11px] text-ink/50" title="{{ $unlock->reason }}">{{ $unlock->reason }}</span>
                            <form method="POST" action="{{ route('admin.frames.unlocks.revoke', $unlock) }}"
                                  onsubmit="return confirm('Revoke this frame? The user can no longer equip it.')">
                                @csrf
                                @method('DELETE')
                                <button class="rounded-lg border border-rose-500/40 px-2.5 py-1 text-xs font-semibold text-rose-700 transition hover:bg-rose-600/10">Revoke</button>
                            </form>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-admin-layout>
