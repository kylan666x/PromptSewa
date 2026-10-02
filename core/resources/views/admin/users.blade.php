<x-admin-layout title="Users">
    @php
        /** @var \Illuminate\Pagination\LengthAwarePaginator $users */
        $roles = [\App\Models\User::ROLE_MEMBER, \App\Models\User::ROLE_CREATOR, \App\Models\User::ROLE_MODERATOR, \App\Models\User::ROLE_ADMIN];
    @endphp

    <form method="GET" class="flex flex-wrap items-center gap-2">
        <input type="text" name="q" value="{{ $search }}" placeholder="Search name or email…"
               class="w-64 rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2 text-sm text-ink placeholder-creak outline-none focus:border-saffron-deep">
        <select name="role" class="rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink/90 outline-none focus:border-saffron-deep">
            <option value="">All roles</option>
            @foreach ($roles as $roleOption)
                <option value="{{ $roleOption }}" @selected($currentRole === $roleOption)>{{ ucfirst($roleOption) }}</option>
            @endforeach
        </select>
        <button class="rounded-xl bg-saffron px-4 py-2 text-sm font-semibold text-ink transition hover:bg-saffron-deep">Filter</button>
    </form>

    <div class="mt-6 overflow-hidden rounded-2xl border border-ink/10">
        <table class="min-w-full divide-y divide-ink/10 text-sm">
            <thead class="bg-paper-deep text-left text-xs uppercase tracking-wider text-ink0">
                <tr>
                    <th class="px-5 py-3 font-semibold">User</th>
                    <th class="hidden px-5 py-3 font-semibold sm:table-cell">Prompts</th>
                    <th class="hidden px-5 py-3 font-semibold md:table-cell">Joined</th>
                    <th class="px-5 py-3 font-semibold">Role</th>
                    <th class="px-5 py-3 font-semibold">Verified</th>
                    <th class="px-5 py-3 font-semibold">Status</th>
                    <th class="px-5 py-3 font-semibold">Act as</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-ink/10 bg-white">
                @forelse ($users as $user)
                    <tr class="transition hover:bg-paper-deep">
                        <td class="px-5 py-3.5">
                            <p class="flex items-center gap-1.5 font-medium text-ink">
                                <x-user-avatar :user="$user" size="sm" :frame="$user->activeFrame"/>
                                {{ $user->name }}
                                <x-verified-badge :user="$user" size="xs"/>
                            </p>
                            <p class="text-xs text-ink0">{{ $user->email }}</p>
                            <x-user-handle :user="$user" size="text-xs" class="opacity-70"/>
                        </td>
                        <td class="hidden px-5 py-3.5 text-ink/60 sm:table-cell">{{ $user->prompts_count }}</td>
                        <td class="hidden px-5 py-3.5 text-ink/60 md:table-cell">{{ $user->created_at->format('M j, Y') }}</td>
                        <td class="px-5 py-3.5">
                            @if (auth()->user()->isAdmin() && $user->id !== auth()->id())
                                <form method="POST" action="{{ route('admin.users.role', $user) }}" class="flex items-center gap-1.5">
                                    @csrf
                                    @method('PATCH')
                                    <select name="role" class="rounded-lg border border-ink/10 bg-paper-deep px-2 py-1 text-xs text-ink/90 outline-none">
                                        @foreach ($roles as $roleOption)
                                            <option value="{{ $roleOption }}" @selected($user->role === $roleOption)>{{ ucfirst($roleOption) }}</option>
                                        @endforeach
                                    </select>
                                    <button class="rounded-lg border border-ink/10 px-2 py-1 text-xs text-ink/80 transition hover:border-saffron-deep hover:text-saffron-deep">Set</button>
                                </form>
                            @else
                                <span class="rounded-md bg-paper-deep px-2 py-0.5 text-xs font-medium text-ink/80">{{ ucfirst($user->role) }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5">
                            @if (auth()->user()->isAdmin())
                                <form method="POST" action="{{ route('admin.users.verified', $user) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit"
                                            class="rounded-lg px-2.5 py-1 text-xs font-semibold transition {{ $user->is_verified ? 'bg-saffron/25 text-saffron-deep hover:bg-saffron/40' : 'border border-ink/10 text-ink/60 hover:border-saffron-deep hover:text-saffron-deep' }}"
                                            @if ($user->is_verified) title="Revoke verified badge" @else title="Issue verified badge" @endif>
                                        {{ $user->is_verified ? '✓ Verified' : 'Verify' }}
                                    </button>
                                </form>
                            @else
                                <x-verified-badge :user="$user" size="xs"/>
                            @endif
                        </td>
                        <td class="px-5 py-3.5">
                            {{-- A4: ban/unban — admin only, never self. --}}
                            @if (auth()->user()->isAdmin() && $user->id !== auth()->id())
                                <form method="POST" action="{{ route('admin.users.banned', $user) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit"
                                            class="rounded-lg px-2.5 py-1 text-xs font-semibold transition {{ $user->isBanned() ? 'bg-rose-600/20 text-rose-700 hover:bg-rose-600/30' : 'border border-ink/10 text-ink/60 hover:border-rose-500 hover:text-rose-700' }}"
                                            @if ($user->isBanned()) title="Lift the ban" @else title="Ban this account" @endif>
                                        {{ $user->isBanned() ? 'Banned — lift' : 'Ban' }}
                                    </button>
                                </form>
                            @elseif ($user->isBanned())
                                <span class="rounded-md bg-rose-600/15 px-2 py-0.5 text-xs font-semibold text-rose-700">Banned</span>
                            @else
                                <span class="text-xs text-ink0">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5">
                            {{-- T3: switch into this account (admin only, never self). --}}
                            @if (auth()->user()->isAdmin() && $user->id !== auth()->id())
                                <form method="POST" action="{{ route('admin.users.impersonate', $user) }}">
                                    @csrf
                                    <button type="submit" class="rounded-lg border border-ink/10 px-2.5 py-1 text-xs font-semibold text-ink/70 transition hover:border-[#1d9bf0] hover:text-[#1d9bf0]" title="Act as this account (audited in impersonations)">
                                        Switch
                                    </button>
                                </form>
                            @else
                                <span class="text-xs text-ink0">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-10 text-center text-ink0">No users match.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $users->links() }}</div>

    {{-- T10 (v1.5.0): demo purge panel — dry-run preview + force. Shells
         pv:purge-demo so the runbook logic and this UI never drift. --}}
    <div class="mt-10 rounded-2xl border border-rose-700/20 bg-rose-50/60 p-6">
        <h3 class="text-sm font-semibold text-rose-800">Demo purge</h3>
        <p class="mt-1 text-xs text-rose-700/80">
            Removes seeded demo identities (hard delete when unused, ban+rename when
            financially linked). Money rows are never deleted. Runbook:
            docs/RUNBOOK-DEMO-PURGE.md.
        </p>
        <div class="mt-4 flex flex-wrap items-center gap-2">
            <form method="POST" action="{{ route('admin.users.purge.preview') }}">
                @csrf
                <button class="rounded-xl border border-rose-700/30 bg-white px-4 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-100">Preview plan (dry run)</button>
            </form>
            <form method="POST" action="{{ route('admin.users.purge.run') }}"
                  onsubmit="return confirm('APPLY demo purge now? Unused demo accounts are deleted and linked ones banned. This follows the dry-run plan exactly.')">
                @csrf
                <button class="rounded-xl bg-rose-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-800">Apply purge</button>
            </form>
        </div>
        @if (session('purge_preview'))
            <pre class="mt-4 max-h-72 overflow-auto rounded-xl bg-ink p-4 font-mono text-[11px] leading-relaxed text-emerald-200">{{ session('purge_preview') }}</pre>
        @endif
    </div>

    {{-- F3 (v1.5.2): "Apply adoption" panel — dry-run preview + force. Shells
         pv:adopt-catalog so the runbook logic and this UI never drift. --}}
    <div class="mt-6 rounded-2xl border border-[#1d9bf0]/25 bg-[#1d9bf0]/5 p-6">
        <h3 class="text-sm font-semibold text-ink">Apply catalog adoption</h3>
        <p class="mt-1 text-xs text-ink/70">
            Reassigns every prompt owned by a seeded demo/bulk identity to the official
            PromptSewa account. Packs, orders, and license grants are untouched; version
            history keeps the original author. Preview first — Apply runs the exact
            dry-run plan.
        </p>
        <div class="mt-4 flex flex-wrap items-center gap-2">
            <form method="POST" action="{{ route('admin.users.adopt.preview') }}">
                @csrf
                <button class="rounded-xl border border-ink/15 bg-white px-4 py-2 text-sm font-semibold text-ink transition hover:border-[#1d9bf0] hover:text-[#1d9bf0]">Preview plan (dry run)</button>
            </form>
            <form method="POST" action="{{ route('admin.users.adopt.run') }}"
                  onsubmit="return confirm('APPLY catalog adoption now? Every candidate prompt moves to the official account. This follows the dry-run plan exactly.')">
                @csrf
                <button class="rounded-xl bg-[#1d9bf0] px-4 py-2 text-sm font-semibold text-white transition hover:brightness-110">Apply adoption</button>
            </form>
        </div>
        @if (session('adoption_preview'))
            <pre class="mt-4 max-h-72 overflow-auto rounded-xl bg-ink p-4 font-mono text-[11px] leading-relaxed text-sky-200">{{ session('adoption_preview') }}</pre>
        @endif
    </div>
</x-admin-layout>
