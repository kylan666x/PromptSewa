<x-admin-layout title="Badges">
    @php
        /** @var \Illuminate\Support\Collection<int, \App\Models\Badge> $badges */
        /** @var \Illuminate\Support\Collection<int, \App\Models\User> $users */
        /** @var list<string> $criteria */
        /** @var array<string, string> $criterionDescriptions */
    @endphp

    <h2 class="text-xl font-bold tracking-tight text-ink">Achievement badges</h2>
    <p class="mt-1 text-xs text-ink/60">
        Auto-awarded by criteria (first_publish, first_sale, sales_10, sales_50, verified, top_rated).
        Images are alpha PNG (512px) — JPEG rejected. Awarded rows are history; populated badges deactivate instead of delete.
    </p>

    {{-- Create / edit --}}
    <form method="POST" action="{{ route('admin.badges.store') }}" enctype="multipart/form-data" class="mt-5 grid gap-4 rounded-2xl border border-ink/10 bg-white p-6 sm:grid-cols-2">
        @csrf
        <div>
            <label class="block text-xs font-medium text-ink/60">Name</label>
            <input type="text" name="name" required maxlength="80" class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink">
        </div>
        <div>
            <label class="block text-xs font-medium text-ink/60">Slug</label>
            <input type="text" name="slug" required maxlength="100" pattern="[a-z0-9-]+" placeholder="first-publish" class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink">
        </div>
        <div x-data="{ criterion: '{{ $criteria[0] }}' }">
            <label class="block text-xs font-medium text-ink/60">Criterion</label>
            <select name="criterion" x-model="criterion" class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink">
                @foreach ($criteria as $criterion)
                    <option value="{{ $criterion }}">{{ str_replace('_', ' ', $criterion) }}</option>
                @endforeach
            </select>
            {{-- F3: one-line description per criterion — staff-judged and
                 manual criteria say so outright, so "no auto-award" is never
                 mistaken for a broken badge. --}}
            @foreach ($criterionDescriptions as $key => $description)
                <p class="mt-1 text-[11px] text-ink/55" x-show="criterion === '{{ $key }}'" @if ($key !== $criteria[0]) style="display: none" @endif>{{ $description }}</p>
            @endforeach
        </div>
        <div>
            <label class="block text-xs font-medium text-ink/60">Image (PNG/WebP, ≤2 MB, transparency required)</label>
            <input type="file" name="image" accept="image/png,image/webp" class="mt-1.5 block w-full text-sm text-ink/70">
        </div>
        <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-ink/60">Description</label>
            <input type="text" name="description" maxlength="255" class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink">
        </div>
        <label class="flex items-center gap-2 text-sm text-ink/80">
            <input type="checkbox" name="is_active" value="1" checked class="size-4 rounded border-ink/20 text-saffron-deep">
            Active (auto-award evaluates active badges only)
        </label>
        <div class="sm:col-span-2">
            <button class="rounded-xl bg-saffron px-4 py-2.5 text-sm font-bold text-ink hover:bg-saffron-deep">Create badge</button>
        </div>
    </form>

    {{-- Manual award --}}
    <form method="POST" action="{{ route('admin.badges.award') }}" class="mt-4 grid gap-4 rounded-2xl border border-sky-500/25 bg-sky-50/60 p-6 sm:grid-cols-4">
        @csrf
        <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-ink/60">Badge</label>
            <select name="badge_id" class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-white px-3.5 py-2.5 text-sm text-ink">
                @foreach ($badges as $badge)
                    <option value="{{ $badge->id }}">{{ $badge->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-ink/60">User</label>
            <select name="user_id" class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-white px-3.5 py-2.5 text-sm text-ink">
                @foreach ($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-ink/60">Reason (required, audited)</label>
            <input type="text" name="reason" required minlength="4" maxlength="255" class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-white px-3.5 py-2.5 text-sm text-ink">
        </div>
        <div class="sm:col-span-4">
            <button class="rounded-xl bg-sky-600 px-4 py-2 text-sm font-bold text-white hover:bg-sky-700">Award manually</button>
        </div>
    </form>

    {{-- F3: idempotent backfill — same code path as `php artisan pv:award-scan`. --}}
    <form method="POST" action="{{ route('admin.badges.scan') }}" class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-ink/10 bg-paper-deep/60 p-4">
        @csrf
        <div>
            <p class="text-sm font-semibold text-ink">Backfill missed awards</p>
            <p class="text-xs text-ink/60">Scans every user against every automatic criterion — idempotent, safe to run any time. Also runs daily at 04:15.</p>
        </div>
        <button class="rounded-xl border border-ink/15 bg-white px-4 py-2 text-sm font-bold text-ink hover:border-saffron-deep hover:text-saffron-deep">Scan now</button>
    </form>

    {{-- Existing badges --}}
    <div class="mt-6 overflow-hidden rounded-2xl border border-ink/10">
        <table class="min-w-full divide-y divide-ink/10 text-sm">
            <thead class="bg-paper-deep text-left text-xs uppercase tracking-wider text-ink0">
                <tr>
                    <th class="px-5 py-3 font-semibold">Badge</th>
                    <th class="px-5 py-3 font-semibold">Criterion</th>
                    <th class="px-5 py-3 font-semibold">Held by</th>
                    <th class="px-5 py-3 font-semibold">Status</th>
                    <th class="px-5 py-3 text-right font-semibold">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-ink/10 bg-white">
                @forelse ($badges as $badge)
                    <tr>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-2.5">
                                @if ($badge->image_path)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($badge->image_path) }}" alt="" class="size-8">
                                @endif
                                <div>
                                    <p class="font-medium text-ink">{{ $badge->name }}</p>
                                    <p class="font-mono text-[10px] text-ink/50">{{ $badge->slug }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3.5">
                            {{-- F3: criterion chip + awarded count, so "nothing
                                 auto-awards" is debuggable at a glance. --}}
                            <span class="rounded-full bg-saffron/15 px-2 py-0.5 font-mono text-[10px] font-bold text-saffron-deep">{{ $badge->criterion }}</span>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="font-mono text-xs font-bold text-ink/80">{{ $badge->user_badges_count }}</span>
                            <span class="text-[10px] uppercase tracking-wide text-ink/45">awarded</span>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="rounded-full px-2 py-0.5 font-mono text-[10px] font-bold {{ $badge->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-paper-deep text-ink/50' }}">
                                {{ $badge->is_active ? 'active' : 'inactive' }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <form method="POST" action="{{ route('admin.badges.destroy', $badge) }}"
                                  onsubmit="return confirm('Delete this badge? Awarded badges are deactivated instead.')">
                                @csrf
                                @method('DELETE')
                                <button class="rounded-lg border border-ink/10 px-2.5 py-1 text-xs font-semibold text-ink/60 hover:border-rose-500 hover:text-rose-700">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-ink/50">No badges yet — create the first one above.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
